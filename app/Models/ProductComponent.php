<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One ingredient of a combo: `quantity` base units of `component` per combo. */
class ProductComponent extends Model
{
    protected $fillable = ['kit_product_id', 'component_product_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function kit(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'kit_product_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id')->withTrashed();
    }
}
