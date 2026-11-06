<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A fiscal establishment of the business (sede): its address and SUNAT code go on every comprobante issued from its cash registers. */
class Branch extends Model
{
    protected $fillable = ['store_id', 'name', 'sunat_code', 'address', 'ubigeo', 'department', 'province', 'district', 'phone', 'is_main', 'is_active'];

    protected $casts = ['is_main' => 'boolean', 'is_active' => 'boolean'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(DocumentSeries::class);
    }

    /** What SUNAT needs from an establishment to accept a comprobante. */
    public function fiscalProblems(): array
    {
        $problems = [];
        if (!preg_match('/^\d{6}$/', (string) $this->ubigeo)) {
            $problems[] = 'ubigeo de 6 dígitos';
        }
        if (trim((string) $this->address) === '') {
            $problems[] = 'dirección';
        }

        return $problems;
    }
}
