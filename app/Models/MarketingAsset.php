<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Un copy o un flyer guardado en la biblioteca de Marketing → Contenido. */
class MarketingAsset extends Model
{
    public const KIND_COPY = 'copy';
    public const KIND_FLYER = 'flyer';

    protected $fillable = [
        'store_id', 'product_id', 'user_id', 'kind', 'template', 'format', 'title', 'content', 'file_path',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
