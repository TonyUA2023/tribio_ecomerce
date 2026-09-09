<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id', 'order_number',        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'customer_country',
        'customer_state',
        'customer_city',
        'customer_zipcode',
        'customer_notes',
        'subtotal', 'discount', 'shipping_cost', 'total', 'currency',
        'status', 'payment_status', 'payment_method',
        'whatsapp_sent', 'whatsapp_sent_at',
        'source', 'internal_notes', 'is_express_shipping'
    ];

    protected $casts = [
        'subtotal'         => 'decimal:2',
        'discount'         => 'decimal:2',
        'shipping_cost'    => 'decimal:2',
        'total'            => 'decimal:2',
        'whatsapp_sent'    => 'boolean',
        'whatsapp_sent_at' => 'datetime',
        'is_express_shipping' => 'boolean',
    ];

    // ─── Helpers ─────────────────────────────────────────────────
    public static function generateOrderNumber(int $storeId): string
    {
        $year = now()->year;
        $count = self::where('store_id', $storeId)->whereYear('created_at', $year)->count() + 1;
        return sprintf('TRB-%d-%06d', $year, $count);
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

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
