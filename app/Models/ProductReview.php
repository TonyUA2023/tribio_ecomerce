<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A verified-purchase review of a product. Rows are written only through
 * App\Services\Reviews\ProductReviewService, which enforces the purchase rule —
 * see the vault note Product-Reviews for the full design.
 */
class ProductReview extends Model
{
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN    = 'hidden';

    public const MAX_COMMENT = 1500;
    public const MAX_REPLY   = 1000;

    protected $fillable = [
        'store_id', 'product_id', 'user_id', 'order_item_id', 'reviewer_name',
        'rating', 'comment', 'status', 'store_reply', 'store_replied_at', 'edited_at',
    ];

    protected $casts = [
        'rating'           => 'integer',
        'store_replied_at' => 'datetime',
        'edited_at'        => 'datetime',
    ];

    // ─── Scopes ──────────────────────────────────────────────────
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    // ─── Helpers ─────────────────────────────────────────────────
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function hasReply(): bool
    {
        return filled($this->store_reply);
    }

    /**
     * How a buyer is named on the public storefront: first name plus the initial of the
     * last one ("María Castillo Pérez" → "María P."). Never an e-mail address — some
     * accounts (Google, quick sign-ups) carry one as their display name.
     */
    public static function publicNameFor(?string $fullName): string
    {
        $fullName = trim((string) $fullName);
        if ($fullName === '' || str_contains($fullName, '@')) {
            return 'Cliente';
        }

        $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = mb_substr($parts[0], 0, 30);

        // "ana" / "MARÍA" were clearly typed without care → "Ana" / "María"; a mixed-case name
        // ("McDonald", "DeLuca") is someone's spelling and stays exactly as written.
        if ($first === mb_strtolower($first) || $first === mb_strtoupper($first)) {
            $first = mb_convert_case($first, MB_CASE_TITLE);
        }

        return count($parts) > 1
            ? $first . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.'
            : $first;
    }

    // ─── Relationships ───────────────────────────────────────────
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        // A product the owner later deleted keeps its reviews in the dashboard history.
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
