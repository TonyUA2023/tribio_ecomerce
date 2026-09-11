<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'variant_id', 'variant_title', 'variant_attributes',
        'product_name', 'product_sku', 'product_image', 'price', 'quantity', 'subtotal',
    ];

    protected $casts = [
        'price'              => 'decimal:2',
        'subtotal'           => 'decimal:2',
        'variant_attributes' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
