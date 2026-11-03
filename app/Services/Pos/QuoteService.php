<?php

namespace App\Services\Pos;

use App\Models\Contact;
use App\Models\Quote;
use App\Models\Store;
use App\Services\Crm\ContactResolver;
use App\Services\Crm\ContactService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Quotes ("proformas", ERP Fase 4c): priced exactly like a counter sale, saved with the
 * lines as offered, printable and sendable by WhatsApp. Charged at the register they keep
 * their prices; nothing is reserved meanwhile.
 */
class QuoteService
{
    public const DEFAULT_DAYS = 7;

    public function __construct(private PosSaleService $sales, private ContactService $timeline, private PriceListService $lists)
    {
    }

    /** @param  array  $data  the register's sale fields (items, discount, customer, price_list) + valid_days, notes */
    public function create(Store $store, array $data, ?int $userId): Quote
    {
        $contact = !empty($data['contact_id']) ? Contact::where('store_id', $store->id)->find($data['contact_id']) : null;
        $list = $this->sales->priceList($data, $contact);
        $lines = $this->sales->lines($store->id, $data['items'], $list);
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $discount = round(min(max(0, (float) ($data['discount'] ?? 0)), $subtotal), 2);

        $items = array_map(fn ($line, $input) => [
            'product_id'    => $line['product']->id,
            'variant_id'    => $line['variant']?->id,
            'unit_id'       => $input['unit_id'] ?? null,
            'name'          => $line['item']['product_name'] . ($line['item']['variant_title'] ? ' · ' . $line['item']['variant_title'] : ''),
            'unit_name'     => $line['item']['unit_name'],
            'quantity'      => (int) $input['quantity'],
            'price'         => round($line['subtotal'] / max(1, (int) $input['quantity']), 2),
            'subtotal'      => $line['subtotal'],
        ], $lines, array_values($data['items']));

        $quote = $this->withNumber($store, fn (string $number) => Quote::create([
            'store_id'          => $store->id,
            'number'            => $number,
            'status'            => 'open',
            'contact_id'        => $contact?->id,
            'user_id'           => $userId,
            'price_list'        => $list,
            'customer_name'     => $contact?->name ?? (trim((string) ($data['customer_name'] ?? '')) ?: PosSaleService::WALK_IN),
            'customer_phone'    => $contact?->phone ?? (ContactResolver::normalizePhone($data['customer_phone'] ?? null) ?: null),
            'customer_document' => $contact?->document_number ?? (trim((string) ($data['customer_document'] ?? '')) ?: null),
            'items'             => $items,
            'subtotal'          => $subtotal,
            'discount'          => $discount,
            'total'             => round($subtotal - $discount, 2),
            'valid_until'       => now('America/Lima')->addDays(max(1, min(90, (int) ($data['valid_days'] ?? self::DEFAULT_DAYS))))->toDateString(),
            'notes'             => filled($data['notes'] ?? null) ? mb_substr(trim($data['notes']), 0, 500) : null,
        ]));

        if ($contact) {
            $this->timeline->log($contact, 'note', "Le enviaste la proforma {$quote->number} por S/ " . number_format((float) $quote->total, 2) . '.', $userId);
        }

        return $quote;
    }

    public function cancel(Quote $quote): void
    {
        if ($quote->status === 'open') {
            $quote->forceFill(['status' => 'cancelled'])->save();
        }
    }

    /** What the register needs to load the quote: its lines (with the quoted price per package) and customer. */
    public function forRegister(Quote $quote, PosCatalog $catalog): array
    {
        $products = \App\Models\Product::where('store_id', $quote->store_id)->where('is_active', true)
            ->whereIn('id', array_column($quote->items, 'product_id'))->with(['variants' => fn ($q) => $q->where('is_active', true)])->get()->keyBy('id');
        $prices = $this->lists->prices($products->keys()->all());

        return [
            'id'        => $quote->id,
            'number'    => $quote->number,
            'priceList' => $quote->price_list ?? 'menor',
            'discount'  => (float) $quote->discount,
            'customer'  => $quote->contact ? ['id' => $quote->contact->id, 'name' => $quote->contact->name, 'relationship' => $quote->contact->relationship] : null,
            'walkIn'    => $quote->contact ? null : ['name' => $quote->customer_name === PosSaleService::WALK_IN ? '' : $quote->customer_name,
                'phone' => (string) $quote->customer_phone, 'document' => (string) $quote->customer_document],
            'lines'     => collect($quote->items)->filter(fn ($item) => isset($products[$item['product_id']]))->map(fn ($item) => [
                'product'   => $catalog->present($products[$item['product_id']], $prices[$item['product_id']] ?? []),
                'variantId' => $item['variant_id'],
                'unitId'    => $item['unit_id'],
                'quantity'  => $item['quantity'],
                'price'     => $item['price'],
            ])->values()->all(),
            'missing'   => collect($quote->items)->reject(fn ($item) => isset($products[$item['product_id']]))->pluck('name')->values()->all(),
        ];
    }

    /** PRO-2026-000001, per store; a concurrent quote taking the number retries with the next one. */
    private function withNumber(Store $store, \Closure $create): Quote
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $count = Quote::where('store_id', $store->id)->where('number', 'like', 'PRO-' . now()->year . '-%')->count() + 1 + $attempt;
            try {
                return DB::transaction(fn () => $create(sprintf('PRO-%d-%06d', now()->year, $count)));
            } catch (UniqueConstraintViolationException $e) {
                continue;
            }
        }

        throw new \RuntimeException('No se pudo numerar la proforma.');
    }
}
