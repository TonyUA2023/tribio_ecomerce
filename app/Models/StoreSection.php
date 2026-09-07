<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'type',
        'order',
        'data',
        'is_active',
    ];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
