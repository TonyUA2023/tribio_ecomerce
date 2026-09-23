<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Where a storefront sale came from (utm_* / fbclid of the visit that ended in the
 * order), recorded by PendingCheckout::materialize(). Tribio's own attribution: it
 * works without reading anything back from Meta.
 */
class OrderAttribution extends Model
{
    public const CHANNEL_META = 'meta';
    public const CHANNEL_OTHER = 'other';

    protected $fillable = [
        'order_id', 'store_id', 'channel', 'utm_source', 'utm_medium', 'utm_campaign',
        'utm_content', 'utm_term', 'fbclid', 'landing_path', 'first_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
