<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A packaging of a product: "Caja" = 100 base units. Stock is always kept in base units. */
class ProductUnit extends Model
{
    protected $fillable = ['product_id', 'name', 'factor', 'barcode'];

    protected $casts = ['factor' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
