<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product's price on a price list (mayorista, distribuidor). Retail ("menor") is products.price. */
class ProductListPrice extends Model
{
    protected $fillable = ['product_id', 'list', 'price'];

    protected $casts = ['price' => 'decimal:2'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
