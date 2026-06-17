<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreAnalytic extends Model
{
    protected $fillable = [
        'store_id', 'date', 'page_views', 'unique_visitors',
        'product_views', 'cart_adds', 'checkouts', 'orders_count', 'revenue',
    ];

    protected $casts = [
        'date'    => 'date',
        'revenue' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
