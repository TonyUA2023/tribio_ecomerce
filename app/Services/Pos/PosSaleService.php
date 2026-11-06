<?php

namespace App\Services\Pos;

use App\Models\Contact;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Services\Crm\ContactResolver;
use App\Services\Crm\ContactService;
use App\Services\Inventory\InventorySchema;
use App\Services\Inventory\StockService;
use App\Services\Pricing\WholesalePricing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A counter sale (ERP Fase 4a). It becomes an ordinary order (source = pos, delivered, paid)
 * so reports, the CRM and reorder reminders see it, with:
 * - lines by product, variant and packaging ("Caja" = 12 units); stock moves in base units
 *   through StockService (lots, FEFO, kits) and a shortfall is logged, never blocked;
 * - the price list of the product (wholesale tiers by total units, the customer's mayorista /
 *   distribuidor list from Fase 4c), editable per line;
 * - split payment: cash with change, Yape, Plin, card, transfer; each method a ledger row
 *   tied to the register shift;
 * - credit ("fiado", Fase 4b): what is not paid stays as the order's balance, split into
 *   installments, within the customer's credit limit;
 * - a client-generated UUID, so a double tap or a retry never sells twice.
 */
class PosSaleService
{
    public const METHODS = [
        'cash'     => 'Efectivo',
        'yape'     => 'Yape',
        'plin'     => 'Plin',
        'card'     => 'Tarjeta',
        'transfer' => 'Transferencia',
    ];

    public const WALK_IN = 'Cliente varios';

    public function __construct(
        private StockService $stock,
        private InventorySchema $inventory,
        private ContactResolver $contacts,
        private PosSchema $schema,
        private ReceivableService $receivables,
        private ContactService $timeline,
        private PriceListService $priceLists,
        private SalesDocuments $documents,
        private \App\Services\Sunat\SunatSchema $sunat,
        private \App\Services\Sunat\Branches $branches,
        private \App\Services\Sunat\ElectronicDocuments $electronic,
    ) {
    }

    /**
     * @param  array{uuid: string, items: list<array{product_id?: int, free?: bool, name?: string, variant_id?: ?int, unit_id?: ?int, quantity: int, price?: ?float}>,
     *               discount?: ?float, contact_id?: ?int, customer_name?: ?string, customer_phone?: ?string, customer_document?: ?string,
     *               payments: list<array{method: string, amount: float}>, notes?: ?string}  $data
     * @return array{0: Order, 1: bool} the order and whether it already existed (same UUID)
     */
    public function sell(RegisterSession $session, array $data, ?int $userId, ?Actor $actor = null): array
    {
        if ($existing = Order::where('pos_uuid', $data['uuid'])->first()) {
            if ((int) $existing->store_id !== (int) $session->store_id) {
                throw ValidationException::withMessages(['uuid' => 'Identificador de venta repetido.']);
            }

            return [$existing, true];
        }
        if (!$session->isOpen()) {
            throw ValidationException::withMessages(['session' => 'Este turno de caja ya se cerró. Abre la caja otra vez.']);
        }

        $contact = !empty($data['contact_id']) ? Contact::where('store_id', $session->store_id)->find($data['contact_id']) : null;
        $lines = $this->lines($session->store_id, $data['items'], $this->priceList($data, $contact));
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $discount = round(min(max(0, (float) ($data['discount'] ?? 0)), $subtotal), 2);
        $total = round($subtotal - $discount, 2);
        $credit = !empty($data['credit']) && $this->schema->creditReady();
        [$payments, $tendered, $change] = $this->payments($data['payments'] ?? [], $total, $credit);
        $plan = $this->creditPlan($data, $contact, round($total - array_sum($payments), 2));

        // Comprobante (nota de venta, boleta o factura): what it needs is checked before anything is written.
        $document = $this->documents->ready() ? $this->documents->normalize($data['document_type'] ?? null) : SalesDocuments::NOTA;
        $this->documents->assertIssuable(Store::findOrFail($session->store_id), $document, $contact?->document_number ?? ($data['customer_document'] ?? null),
            $contact?->name ?? ($data['customer_name'] ?? null), $total);

        try {
            $order = DB::transaction(function () use ($session, $data, $lines, $subtotal, $discount, $total, $payments, $tendered, $change, $contact, $plan, $userId, $actor, $document) {
                $order = new Order();
                $order->forceFill([
                    'store_id'                 => $session->store_id,
                    'user_id'                  => null,
                    'order_number'             => Order::generateOrderNumber($session->store_id),
                    'customer_name'            => $contact?->name ?? (trim((string) ($data['customer_name'] ?? '')) ?: self::WALK_IN),
                    'customer_phone'           => $contact?->phone ?? (ContactResolver::normalizePhone($data['customer_phone'] ?? null) ?: null),
                    'customer_email'           => $contact?->email,
                    'customer_document_type'   => $contact?->document_type ?? (!empty($data['customer_document']) ? (strlen($data['customer_document']) === 11 ? 'RUC' : 'DNI') : null),
                    'customer_document_number' => $contact?->document_number ?? (trim((string) ($data['customer_document'] ?? '')) ?: null),
                    'customer_country'         => 'PE',
                    'customer_notes'           => filled($data['notes'] ?? null) ? mb_substr(trim($data['notes']), 0, 255) : null,
                    'customer_address'         => filled($data['customer_address'] ?? null) ? mb_substr(trim($data['customer_address']), 0, 255) : null,
                    'subtotal'                 => $subtotal,
                    'discount'                 => $discount,
                    'shipping_cost'            => 0,
                    'total'                    => $total,
                    'currency'                 => 'PEN',
                    'status'                   => 'delivered',
                    'payment_status'           => $total > 0 ? 'pending' : 'paid',
                    'payment_method'           => count($payments) === 1 ? array_key_first($payments) : ($payments ? 'mixto' : null),
                    'source'                   => 'pos',
                    'register_session_id'      => $session->id,
                    'pos_uuid'                 => $data['uuid'],
                    'cash_tendered'            => $tendered,
                    'change_given'             => $change,
                    'contact_id'               => $contact?->id,
                ] + ($plan ? ['due_date' => end($plan)['due_date']] : []) + ($actor ? $actor->columns() : [])
                  + (!empty($data['offline_at']) ? $this->offlineTimestamps($data['offline_at']) : []))->save();

                if ($this->documents->ready()) {
                    $register = Register::find($session->register_id);
                    if ($this->sunat->ready() && $register) {
                        $order->forceFill(['branch_id' => $this->branches->of($register, $order->store)->id])->save();
                    }
                    $this->documents->issue($order, $document, $register);
                    // Boleta/factura marked for SUNAT (by the sale, or by the store's setting): queued with the sale.
                    $this->electronic->queue($order, $document, $data['send_sunat'] ?? null);
                }

                $order->items()->createMany(array_map(fn ($line) => $line['item'], $lines));
                if ($plan) {
                    $order->installments()->createMany($plan);
                }
                if (!empty($data['quote_id']) && $this->priceLists->ready()) {
                    Quote::where('store_id', $session->store_id)->whereKey($data['quote_id'])->where('status', 'open')
                        ->update(['status' => 'sold', 'order_id' => $order->id, 'updated_at' => now()]);
                }
                $order->store()->increment('total_orders');

                foreach ($lines as $line) {
                    if (!$line['product']) {
                        continue; // a sale by amount has no stock
                    }
                    $this->stock->sell($line['product'], $line['variant'], $line['base_quantity'], $order->order_number);
                }
                foreach ($payments as $method => $amount) {
                    $order->recordPayment($amount, 'full', 'pos', null, $userId, null)
                        ->forceFill(['method' => $method, 'register_session_id' => $session->id] + ($actor ? $actor->columns('staff_id', null) : []))->save();
                }

                return $order;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // The same UUID raced in from a second tap: the first one won.
            if ($existing = Order::where('pos_uuid', $data['uuid'])->where('store_id', $session->store_id)->first()) {
                return [$existing, true];
            }
            throw $e;
        }

        // Marked for SUNAT: it goes out right after the cashier has her answer.
        $this->electronic->dispatch($order);

        // A customer picked by name only has nothing the resolver can match: keep the link and its totals.
        if ($contact && (int) $order->fresh()->contact_id !== (int) $contact->id) {
            Order::whereKey($order->id)->update(['contact_id' => $contact->id]);
        }
        if ($contact) {
            $this->contacts->refreshTotals($contact->fresh());
        }
        if ($plan && ($debtor = $order->fresh()->contact)) {
            $owed = round(array_sum(array_column($plan, 'amount')), 2);
            $this->timeline->log($debtor, 'credit', sprintf('Compró al crédito (%s): quedó debiendo S/ %s en %s, desde el %s.', $order->order_number,
                number_format($owed, 2), count($plan) === 1 ? '1 pago' : count($plan) . ' cuotas', Carbon::parse($plan[0]['due_date'])->format('d/m/Y')), $userId);
        }

        return [$order->fresh(['items', 'payments']), false];
    }

    /**
     * A sale rung up without connection keeps the time it really happened (the client clock,
     * accepted when it is within the last 3 days and not in the future).
     */
    private function offlineTimestamps(string $at): array
    {
        try {
            $when = Carbon::parse($at);
        } catch (\Throwable $e) {
            return [];
        }

        return $when->lte(now()->addMinutes(5)) && $when->gte(now()->subDays(3)) ? ['created_at' => $when->min(now())] : [];
    }

    /**
     * The price list of the sale: the one chosen at the register (`price_list`, "menor" =
     * retail), else the customer's.
     */
    public function priceList(array $data, ?Contact $contact): ?string
    {
        if (!$this->priceLists->ready()) {
            return null;
        }
        if (array_key_exists('price_list', $data)) {
            return isset(PriceListService::LISTS[$data['price_list'] ?? '']) ? $data['price_list'] : null;
        }

        return $this->priceLists->forContact($contact);
    }

    /**
     * Priced lines, for a sale or a quote. Wholesale tiers count every line of the same product
     * together, in base units; a price list applies unless a tier is cheaper.
     *
     * @return list<array{product: ?Product, variant: ?ProductVariant, base_quantity: int, subtotal: float, item: array}>
     */
    public function lines(int $storeId, array $items, ?string $list = null): array
    {
        $products = Product::where('store_id', $storeId)->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');
        $unitsReady = $this->inventory->unitsReady();
        $resolved = [];
        foreach (array_values($items) as $i => $item) {
            // "Cobrar por monto" (modo simple): a line with just a name and a price, no product and no stock.
            if (!empty($item['free'])) {
                $price = round((float) ($item['price'] ?? 0), 2);
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                if ($price <= 0 || $price > 99999) {
                    throw ValidationException::withMessages(["items.{$i}" => 'Escribe el monto a cobrar.']);
                }
                $resolved[] = ['free' => true, 'name' => mb_substr(trim((string) ($item['name'] ?? '')) ?: 'Venta por monto', 0, 120), 'price' => $price, 'quantity' => $quantity];
                continue;
            }
            $product = $products[$item['product_id']] ?? null;
            if (!$product || !$product->is_active) {
                throw ValidationException::withMessages(["items.{$i}" => 'Ese producto ya no está a la venta.']);
            }
            if ($product->isMadeToOrder()) {
                throw ValidationException::withMessages(["items.{$i}" => "«{$product->name}» se vende por encargo: regístralo como pedido."]);
            }

            $variant = null;
            if (!empty($item['variant_id'])) {
                $variant = ProductVariant::where('product_id', $product->id)->find($item['variant_id']);
                if (!$variant) {
                    throw ValidationException::withMessages(["items.{$i}" => "Elige una variante de «{$product->name}»."]);
                }
            } elseif ($product->has_variants && $product->variants()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(["items.{$i}" => "Elige una variante de «{$product->name}»."]);
            }

            $unit = $unitsReady && !empty($item['unit_id']) ? ProductUnit::where('product_id', $product->id)->find($item['unit_id']) : null;
            if (!empty($item['unit_id']) && !$unit) {
                throw ValidationException::withMessages(["items.{$i}" => "Esa presentación de «{$product->name}» ya no existe."]);
            }

            $quantity = (int) $item['quantity'];
            $factor = $unit ? max(1, (int) $unit->factor) : 1;
            $resolved[] = compact('product', 'variant', 'unit', 'quantity', 'factor') + ['price' => $item['price'] ?? null];
        }

        // Base units per product, for the wholesale tier.
        $totals = [];
        foreach ($resolved as $line) {
            if (!empty($line['free'])) {
                continue;
            }
            $totals[$line['product']->id] = ($totals[$line['product']->id] ?? 0) + $line['quantity'] * $line['factor'];
        }
        $tiers = WholesalePricing::ready();
        $listPrices = $this->priceLists->prices(array_unique(array_map(fn ($line) => $line['product']->id, array_filter($resolved, fn ($line) => empty($line['free'])))));

        return array_map(function (array $line) use ($totals, $tiers, $list, $listPrices) {
            if (!empty($line['free'])) {
                $subtotal = round($line['price'] * $line['quantity'], 2);

                return [
                    'product' => null, 'variant' => null, 'base_quantity' => $line['quantity'], 'subtotal' => $subtotal,
                    'item' => [
                        'product_id' => null, 'variant_id' => null, 'variant_title' => null, 'variant_attributes' => null,
                        'product_name' => $line['name'], 'product_sku' => null, 'product_image' => null,
                        'price' => $line['price'], 'quantity' => $line['quantity'], 'subtotal' => $subtotal, 'unit_name' => null, 'unit_quantity' => null,
                    ],
                ];
            }
            ['product' => $product, 'variant' => $variant, 'unit' => $unit, 'quantity' => $quantity, 'factor' => $factor] = $line;
            $base = (float) ($variant?->price ?: $product->price);
            $ratio = $tiers ? WholesalePricing::factor($product, $totals[$product->id]) : 1.0;
            $unitPrice = $base * $ratio;
            $listed = $this->priceLists->unitPrice($product, $base, $list, $listPrices);
            if ($listed !== null) {
                $unitPrice = min($unitPrice, $listed);
            }
            $packagePrice = $line['price'] !== null ? round((float) $line['price'], 2) : round($unitPrice * $factor, 2);
            $subtotal = round($packagePrice * $quantity, 2);
            $baseQuantity = $quantity * $factor;

            return [
                'product'       => $product,
                'variant'       => $variant,
                'base_quantity' => $baseQuantity,
                'subtotal'      => $subtotal,
                'item'          => [
                    'product_id'         => $product->id,
                    'variant_id'         => $variant?->id,
                    'variant_title'      => $variant?->title,
                    'variant_attributes' => $variant?->getAttribute('attributes'),
                    'product_name'       => $product->name,
                    'product_sku'        => $variant?->sku ?: $product->sku,
                    'product_image'      => $product->image_path,
                    // Quantity in base units (stock, reports); the packaging is kept for the ticket.
                    'price'              => round($subtotal / max(1, $baseQuantity), 2),
                    'quantity'           => $baseQuantity,
                    'subtotal'           => $subtotal,
                    'unit_name'          => $unit?->name,
                    'unit_quantity'      => $unit ? $quantity : null,
                ],
            ];
        }, $resolved);
    }

    /**
     * The installments of what is left unpaid. Credit needs a customer the store can find
     * again (picked, or named with a phone or document) and stays within their limit.
     *
     * @return ?list<array{number: int, due_date: string, amount: float}>
     */
    private function creditPlan(array $data, ?Contact $contact, float $owed): ?array
    {
        if ($owed <= 0.005) {
            return null;
        }
        $terms = $data['credit'] ?? [];
        $known = $contact || (filled($data['customer_name'] ?? null) && (filled($data['customer_phone'] ?? null) || filled($data['customer_document'] ?? null)));
        if (!$known) {
            throw ValidationException::withMessages(['credit' => 'Para fiar, elige al cliente o anota su nombre y su celular o DNI.']);
        }
        if ($contact) {
            $this->receivables->assertWithinLimit($contact, $owed);
        }

        $today = $this->receivables->today();
        $first = !empty($terms['first_due']) ? Carbon::parse($terms['first_due'], 'America/Lima')->startOfDay() : $today->copy()->addDays(ReceivableService::DEFAULT_DAYS);
        if ($first->lt($today)) {
            throw ValidationException::withMessages(['credit' => 'La fecha de pago no puede ser anterior a hoy.']);
        }

        return $this->receivables->plan($owed, $first, (int) ($terms['installments'] ?? 1), (int) ($terms['every_days'] ?? 30));
    }

    /**
     * Applied amount per method. Cash may exceed what is left to pay (the change); the other
     * methods may not. Without credit everything is paid at the counter; on credit the cash
     * given is kept whole and the rest is owed.
     *
     * @return array{0: array<string, float>, 1: ?float, 2: ?float} applied per method, cash tendered, change
     */
    private function payments(array $payments, float $total, bool $credit = false): array
    {
        $tendered = 0.0;
        $other = [];
        foreach ($payments as $payment) {
            $amount = round((float) ($payment['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            if (($payment['method'] ?? null) === 'cash') {
                $tendered += $amount;
            } else {
                $other[$payment['method']] = ($other[$payment['method']] ?? 0) + $amount;
            }
        }

        $nonCash = round(array_sum($other), 2);
        if ($nonCash > $total + 0.005) {
            throw ValidationException::withMessages(['payments' => 'Con Yape, Plin, tarjeta o transferencia no se da vuelto: el monto supera el total.']);
        }
        $cashDue = round($total - $nonCash, 2);
        if ($tendered + 0.005 < $cashDue) {
            if ($credit) {
                return [($tendered > 0 ? ['cash' => round($tendered, 2)] : []) + array_map(fn ($amount) => round($amount, 2), $other),
                    $tendered > 0 ? round($tendered, 2) : null, $tendered > 0 ? 0.0 : null];
            }
            throw ValidationException::withMessages(['payments' => sprintf('Falta cobrar S/ %s.', number_format($cashDue - $tendered, 2))]);
        }

        $applied = ($cashDue > 0 ? ['cash' => $cashDue] : []) + array_map(fn ($amount) => round($amount, 2), $other);

        return [$applied, $tendered > 0 ? round($tendered, 2) : null, $tendered > 0 ? round($tendered - $cashDue, 2) : null];
    }
}
