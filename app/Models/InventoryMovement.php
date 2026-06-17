<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id', 'store_id', 'type', 'quantity',
        'stock_before', 'stock_after', 'reason', 'reference', 'user_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'in'         => 'Entrada',
            'out'        => 'Salida',
            'adjustment' => 'Ajuste',
            'sale'       => 'Venta',
            'return'     => 'Devolución',
            'loss'       => 'Pérdida',
            default      => ucfirst($this->type),
        };
    }
}
