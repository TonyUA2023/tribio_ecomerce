<?php

namespace App\Services\Reviews;

use App\Helpers\TranslationHelper;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Product reviews: who may write one, writing/editing it, the numbers the storefront
 * shows, and what the store owner can do with them (hide, reply).
 *
 * The one rule: a review needs a real purchase. A Tribio Pass account may review a
 * product only after an order of theirs containing it reached `delivered`, and only
 * once per product (buying it again edits the same review). Because of that rule every
 * review is a verified purchase — there is no "verified" flag.
 */
class ProductReviewService
{
    public const PER_PAGE = 8;
    public const PAGE_NAME = 'resenas';

    // What the viewer of a product page can do about reviews.
    public const STATE_GUEST             = 'guest';
    public const STATE_OWN_STORE         = 'own_store';
    public const STATE_CAN_REVIEW        = 'can_review';
    public const STATE_REVIEWED          = 'reviewed';
    public const STATE_AWAITING_DELIVERY = 'awaiting_delivery';
    public const STATE_NOT_PURCHASED     = 'not_purchased';

    private ?bool $available = null;

    /**
     * Whether the reviews tables exist yet. The deploy entrypoint does not run migrations and
     * the local .env points at the production database, so code can briefly run ahead of its
     * migration; callers that must never fail because of that (purchase history) check this.
     */
    public function isAvailable(): bool
    {
        return $this->available ??= Schema::hasTable('product_reviews');
    }

    // ─── Eligibility ─────────────────────────────────────────────

    /**
     * The purchase that unlocks reviewing this product: the oldest order line of the
     * buyer's for it inside a delivered order (stable proof — buying again doesn't move it).
     */
    public function qualifyingOrderItem(User $user, Product $product): ?OrderItem
    {
        return OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->boughtBy($user)->where('status', 'delivered'))
            ->oldest('id')
            ->first();
    }

    /**
     * @return array{state: string, review: ?ProductReview, item: ?OrderItem}
     */
    public function viewerState(?User $user, Product $product): array
    {
        $result = fn (string $state, ?ProductReview $review = null, ?OrderItem $item = null) =>
            ['state' => $state, 'review' => $review, 'item' => $item];

        if (!$user) {
            return $result(self::STATE_GUEST);
        }

        if (Store::whereKey($product->store_id)->where('user_id', $user->id)->exists()) {
            return $result(self::STATE_OWN_STORE);
        }

        $existing = ProductReview::where('user_id', $user->id)->where('product_id', $product->id)->first();
        if ($existing) {
            return $result(self::STATE_REVIEWED, $existing);
        }

        if ($item = $this->qualifyingOrderItem($user, $product)) {
            return $result(self::STATE_CAN_REVIEW, null, $item);
        }

        $inTransit = Order::boughtBy($user)
            ->whereNotIn('status', ['delivered', 'cancelled', 'refunded'])
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        return $result($inTransit ? self::STATE_AWAITING_DELIVERY : self::STATE_NOT_PURCHASED);
    }

    public function messageForState(string $state): string
    {
        $en = TranslationHelper::isEn();

        return match ($state) {
            self::STATE_GUEST             => $en ? 'Sign in with your Tribio Pass to leave a review.' : 'Inicia sesión con tu Tribio Pass para dejar una reseña.',
            self::STATE_OWN_STORE         => $en ? "You can't review products from your own store." : 'No puedes reseñar productos de tu propia tienda.',
            self::STATE_AWAITING_DELIVERY => $en ? "You'll be able to review this product once your order is delivered." : 'Podrás calificar este producto cuando tu pedido sea entregado.',
            default                       => $en ? 'Only customers who bought this product and received it can review it.' : 'Solo pueden reseñar quienes compraron este producto y ya lo recibieron.',
        };
    }

    // ─── Buyer: write / edit ─────────────────────────────────────

    /**
     * Creates the buyer's review of the product, or edits it when they already left one.
     * Reviews publish immediately; an owner-hidden review stays hidden after an edit.
     *
     * @return array{review: ProductReview, created: bool}
     * @throws ReviewNotAllowedException when the buyer has no delivered purchase of it
     */
    public function submit(User $user, Product $product, int $rating, ?string $comment): array
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('La calificación debe estar entre 1 y 5.');
        }

        $comment = $this->cleanText($comment, ProductReview::MAX_COMMENT);
        $viewer = $this->viewerState($user, $product);

        if (!in_array($viewer['state'], [self::STATE_CAN_REVIEW, self::STATE_REVIEWED], true)) {
            throw new ReviewNotAllowedException($this->messageForState($viewer['state']), $viewer['state']);
        }

        if ($review = $viewer['review']) {
            return ['review' => $this->applyEdit($review, $rating, $comment), 'created' => false];
        }

        try {
            $review = ProductReview::create([
                'store_id'      => $product->store_id,
                'product_id'    => $product->id,
                'user_id'       => $user->id,
                'order_item_id' => $viewer['item']->id,
                'reviewer_name' => ProductReview::publicNameFor($user->name),
                'rating'        => $rating,
                'comment'       => $comment,
                'status'        => ProductReview::STATUS_PUBLISHED,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A double click / second device beat us to it: this submission becomes an edit.
            $review = ProductReview::where('user_id', $user->id)->where('product_id', $product->id)->firstOrFail();

            return ['review' => $this->applyEdit($review, $rating, $comment), 'created' => false];
        }

        return ['review' => $review, 'created' => true];
    }

    private function applyEdit(ProductReview $review, int $rating, ?string $comment): ProductReview
    {
        $review->fill(['rating' => $rating, 'comment' => $comment]);

        if ($review->isDirty(['rating', 'comment'])) {
            $review->edited_at = now();
        }

        $review->save();

        return $review;
    }

    // ─── Storefront read model ───────────────────────────────────

    /**
     * Rating summary for the product page, computed from the published rows themselves
     * (not the cached counters) so the average, the bars and the list can never disagree.
     *
     * @return array{count: int, average: float, distribution: array<int,int>}
     */
    public function summary(Product $product): array
    {
        $rows = ProductReview::published()
            ->where('product_id', $product->id)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rows as $rating => $total) {
            if (isset($distribution[(int) $rating])) {
                $distribution[(int) $rating] = (int) $total;
            }
        }

        $count = array_sum($distribution);
        $sum = 0;
        foreach ($distribution as $rating => $total) {
            $sum += $rating * $total;
        }

        return [
            'count'        => $count,
            'average'      => $count > 0 ? round($sum / $count, 1) : 0.0,
            'distribution' => $distribution,
        ];
    }

    /**
     * Everything the reviews section of any template needs, in one place.
     *
     * @return array{summary: array, reviews: LengthAwarePaginator, viewer: array, page_name: string}
     */
    public function storefrontData(Product $product, ?User $viewer): array
    {
        $reviews = ProductReview::published()
            ->where('product_id', $product->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], self::PAGE_NAME)
            ->withQueryString()
            ->fragment(self::PAGE_NAME);

        return [
            'summary'   => $this->summary($product),
            'reviews'   => $reviews,
            'viewer'    => $this->viewerState($viewer, $product),
            'page_name' => self::PAGE_NAME,
        ];
    }

    /** The JSON shape shared by the web hub and the mobile API. */
    public function transform(ProductReview $review): array
    {
        return [
            'id'               => $review->id,
            'product_id'       => $review->product_id,
            'rating'           => (int) $review->rating,
            'comment'          => $review->comment,
            'status'           => $review->status,
            'store_reply'      => $review->store_reply,
            'store_replied_at' => $review->store_replied_at?->toIso8601String(),
            'edited_at'        => $review->edited_at?->toIso8601String(),
            'created_at'       => $review->created_at?->toIso8601String(),
        ];
    }

    // ─── Store owner: moderate / reply ───────────────────────────

    public function setVisibility(ProductReview $review, bool $published): ProductReview
    {
        $review->status = $published ? ProductReview::STATUS_PUBLISHED : ProductReview::STATUS_HIDDEN;
        $review->save();

        return $review;
    }

    public function reply(ProductReview $review, string $reply): ProductReview
    {
        $reply = $this->cleanText($reply, ProductReview::MAX_REPLY);
        if ($reply === null) {
            throw new InvalidArgumentException('La respuesta no puede estar vacía.');
        }

        $review->forceFill(['store_reply' => $reply, 'store_replied_at' => now()])->save();

        return $review;
    }

    public function removeReply(ProductReview $review): ProductReview
    {
        $review->forceFill(['store_reply' => null, 'store_replied_at' => null])->save();

        return $review;
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /** Trims, normalizes line breaks, drops control characters and caps the length. Empty → null. */
    public function cleanText(?string $text, int $max): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $text);
        // Only C0/C1 control characters: the wider "other" class would also strip the
        // zero-width joiner that glues family/profession emoji together.
        $text = preg_replace('/[^\P{Cc}\n]+/u', '', $text) ?? '';
        $text = preg_replace("/\n{3,}/", "\n\n", trim($text)) ?? '';
        $text = mb_substr($text, 0, $max);

        return $text === '' ? null : $text;
    }
}
