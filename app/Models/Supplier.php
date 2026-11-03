<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Someone the store buys from. Query through $store->suppliers(). */
class Supplier extends Model
{
    protected $fillable = ['store_id', 'name', 'document_number', 'contact_name', 'phone', 'email', 'notes'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);
        if (strlen($digits) === 9 && $digits[0] === '9') {
            $digits = '51' . $digits;
        }

        return strlen($digits) >= 8 ? 'https://wa.me/' . $digits : null;
    }
}
