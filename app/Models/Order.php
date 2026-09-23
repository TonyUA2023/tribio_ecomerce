<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'flow_order_id', 'flow_token',
        'store_id', 'user_id', 'order_number',        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_document_type',
        'customer_document_number',
        'customer_address',
        'customer_country',
        'customer_state',
        'customer_city',
        'customer_zipcode',
        'customer_notes',
        'subtotal', 'discount', 'shipping_cost', 'total', 'currency',
        'status', 'payment_status', 'payment_method', 'paypal_order_id',
        'whatsapp_sent', 'whatsapp_sent_at',
        'source', 'internal_notes', 'is_express_shipping'
    ];

    protected $hidden = ['flow_token'];

    protected $casts = [
        'subtotal'         => 'decimal:2',
        'discount'         => 'decimal:2',
        'shipping_cost'    => 'decimal:2',
        'total'            => 'decimal:2',
        'amount_paid'      => 'decimal:2',
        'balance_due'      => 'decimal:2',
        'whatsapp_sent'    => 'boolean',
        'whatsapp_sent_at' => 'datetime',
        'is_express_shipping' => 'boolean',
    ];

    protected static function booted(): void
    {
        // balance_due is derived, never set by hand: recomputed on every save so a status
        // change (e.g. cancelled) or a new ledger entry can't leave it stale.
        static::saving(function (Order $order) {
            $order->balance_due = $order->computeBalanceDue();
        });
    }

    // ─── Payments ledger ─────────────────────────────────────────
    /**
     * Credits money to this order. A gateway reference is credited at most once (a
     * replayed webhook returns the existing row). amount_paid is then re-summed from the
     * ledger rather than incremented, so it can never drift from the recorded payments.
     */
    public function recordPayment(
        float $amount,
        string $kind,
        string $gateway,
        ?string $gatewayRef = null,
        ?int $recordedBy = null,
        ?string $notes = null,
    ): OrderPayment {
        $attributes = [
            'kind' => $kind, 'amount' => round($amount, 2), 'currency' => $this->currency ?: 'PEN',
            'status' => OrderPayment::STATUS_PAID, 'paid_at' => now(),
            'recorded_by' => $recordedBy, 'notes' => $notes,
        ];

        $payment = $gatewayRef !== null && $gatewayRef !== ''
            ? OrderPayment::firstOrCreate(['gateway' => $gateway, 'gateway_ref' => $gatewayRef], $attributes + ['order_id' => $this->id])
            : $this->payments()->create($attributes + ['gateway' => $gateway]);

        if ((int) $payment->order_id !== (int) $this->id) {
            throw new \DomainException("La referencia {$gateway}:{$gatewayRef} ya está acreditada al pedido #{$payment->order_id}.");
        }

        $this->refreshPaymentTotals();

        return $payment;
    }

    public function refreshPaymentTotals(): void
    {
        $this->forceFill([
            'amount_paid' => round((float) $this->payments()->where('status', OrderPayment::STATUS_PAID)->sum('amount'), 2),
        ])->save();
    }

    /** What is still owed on a live order; closed or failed orders owe nothing. */
    public function computeBalanceDue(): float
    {
        if (in_array($this->status, ['cancelled', 'refunded'], true)
            || in_array($this->payment_status, ['failed', 'refunded'], true)) {
            return 0.0;
        }

        return max(0.0, round((float) $this->total - (float) $this->amount_paid, 2));
    }

    // ─── Helpers ─────────────────────────────────────────────────
    /**
     * order_number is globally unique (DB constraint) but the sequence itself is scoped
     * per store per year, so two different stores placing their Nth order of the year
     * both compute the same candidate — the loop below guards against that collision
     * (real, hit while testing the multi-store feature: store A's first-ever order and
     * store B's first-ever order both generated 'TRB-2026-000001').
     */
    public static function generateOrderNumber(int $storeId): string
    {
        $year = now()->year;
        $count = self::where('store_id', $storeId)->whereYear('created_at', $year)->count();

        // A gateway checkout reserves its number in pending_checkouts long before (or
        // without ever) becoming an Order — an abandoned payment page keeps it forever —
        // so both tables must be skipped, or every later checkout of the store collides.
        do {
            $count++;
            $candidate = sprintf('TRB-%d-%06d', $year, $count);
        } while (self::where('order_number', $candidate)->exists()
            || PendingCheckout::where('reference', $candidate)->exists());

        return $candidate;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'    => 'Pendiente',
            'confirmed'  => 'Confirmado',
            'processing' => 'En proceso',
            'shipped'    => 'Enviado',
            'delivered'  => 'Entregado',
            'cancelled'  => 'Cancelado',
            'refunded'   => 'Reembolsado',
            default      => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'    => 'yellow',
            'confirmed'  => 'blue',
            'processing' => 'purple',
            'shipped'    => 'indigo',
            'delivered'  => 'green',
            'cancelled'  => 'red',
            'refunded'   => 'gray',
            default      => 'gray',
        };
    }

    /** Maps the status to one of the dashboard's existing muted badge pills (app.css). */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending'    => 'badge-gold',
            'confirmed', 'shipped' => 'badge-blue',
            'processing' => 'badge-purple',
            'delivered'  => 'badge-green',
            'cancelled'  => 'badge-red',
            default      => 'badge-gray',
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'paid'     => 'Pagado',
            'pending'  => 'Pago pendiente',
            'failed'   => 'Pago fallido',
            'refunded' => 'Reembolsado',
            default    => ucfirst((string) $this->payment_status),
        };
    }

    public function getPaymentStatusBadgeAttribute(): string
    {
        return match ($this->payment_status) {
            'paid'     => 'badge-green',
            'pending'  => 'badge-gold',
            'failed'   => 'badge-red',
            default    => 'badge-gray',
        };
    }

    /** Human label for the gateway/channel the buyer chose at checkout. */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'mercadopago'       => 'Mercado Pago · Tarjeta',
            'mercadopago_other' => 'Mercado Pago · Yape / PagoEfectivo / banca',
            'card'              => 'Tarjeta',
            'paypal'            => 'PayPal',
            'flow'              => 'Flow',
            'whatsapp'          => 'WhatsApp / Pago directo',
            null, ''            => 'No especificado',
            default             => ucfirst(str_replace('_', ' ', $this->payment_method)),
        };
    }

    public function getCurrencySymbolAttribute(): string
    {
        return \App\Helpers\CurrencyHelper::symbol($this->currency ?: 'PEN');
    }

    public function money(float|string|null $amount): string
    {
        return $this->currency_symbol . ' ' . \App\Helpers\CurrencyHelper::format((float) $amount, $this->currency ?: 'PEN');
    }

    public function getCountryNameAttribute(): ?string
    {
        if (!$this->customer_country) {
            return null;
        }

        return \App\Helpers\CurrencyHelper::getCountryInfo($this->customer_country)['name'] ?? $this->customer_country;
    }

    /** "Av. X 123, Miraflores, Lima, Perú" — only the parts the buyer actually filled in. */
    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->customer_address,
            $this->customer_city,
            $this->customer_state,
            $this->country_name,
            $this->customer_zipcode ? "CP {$this->customer_zipcode}" : null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * Plain-text shipping block the store owner pastes into a courier's form (Olva,
     * Shalom, etc.) — exactly the fields they ask for, in the order they ask.
     */
    public function shippingLabelText(): string
    {
        $document = $this->customer_document_number
            ? trim(($this->customer_document_type ?: 'DNI') . ' ' . $this->customer_document_number)
            : null;

        return implode("\n", array_filter([
            "Pedido: #{$this->order_number}",
            "Destinatario: {$this->customer_name}",
            $document ? "Documento: {$document}" : null,
            $this->customer_phone ? "Celular: {$this->customer_phone}" : null,
            $this->customer_email ? "Correo: {$this->customer_email}" : null,
            $this->full_address ? "Dirección: {$this->full_address}" : null,
            $this->customer_notes ? "Referencia / notas: {$this->customer_notes}" : null,
        ]));
    }

    public function buildWhatsappMessage(): string
    {
        $items = $this->items->map(function ($item) {
            return "• {$item->quantity}x {$item->product_name} (S/. " . number_format($item->price, 2) . " c/u) - S/. " . number_format($item->subtotal, 2);
        })->join("\n");

        return urlencode(
            "🛍️ *¡Nuevo pedido - {$this->store->name}!*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━\n" .
            "📋 *Pedido:* #{$this->order_number}\n" .
            "👤 *Cliente:* {$this->customer_name}\n" .
            ($this->customer_document_number ? "🪪 *" . ($this->customer_document_type ?: 'DNI') . ":* {$this->customer_document_number}\n" : '') .
            ($this->customer_phone ? "📞 *Teléfono:* {$this->customer_phone}\n" : '') .
            ($this->customer_address ? "📍 *Dirección:* {$this->customer_address}\n" : '') .
            "━━━━━━━━━━━━━━━━━━━━━━\n" .
            "📦 *Detalle del Pedido:*\n{$items}\n" .
            "━━━━━━━━━━━━━━━━━━━━━━\n" .
            "💰 *Total:* S/. " . number_format($this->total, 2) . "\n" .
            ($this->customer_notes ? "📝 *Notas:* {$this->customer_notes}\n" : '') .
            "━━━━━━━━━━━━━━━━━━━━━━\n" .
            "⚡ _Pedido generado a través de Tribio_"
        );
    }

    // ─── Relationships ───────────────────────────────────────────
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }
}
