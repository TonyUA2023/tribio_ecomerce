<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The series of one document type of a store (B001…) and the last correlative number issued. */
class DocumentSeries extends Model
{
    protected $table = 'document_series';

    protected $fillable = ['store_id', 'branch_id', 'register_id', 'type', 'series', 'last_number', 'electronic'];

    protected $casts = ['last_number' => 'integer', 'electronic' => 'boolean'];

    /** Does this series get reported to SUNAT? (true until the flag exists) */
    public function isElectronic(): bool
    {
        return !array_key_exists('electronic', $this->attributes) || (bool) $this->electronic;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
