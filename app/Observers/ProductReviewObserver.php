<?php

namespace App\Observers;

use App\Mail\ProductReviewReceived;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProductReviewObserver
{
    /**
     * A new review always tells the store owner — queued, so a mail outage never blocks or
     * slows the buyer (same approach as OrderObserver). Edits and hides stay silent.
     */
    public function created(ProductReview $review): void
    {
        $review->loadMissing('store');

        $storeEmail = $review->store?->contact_email ?: $review->store?->email;
        if (!$storeEmail) {
            return;
        }

        try {
            Mail::to($storeEmail)->queue((new ProductReviewReceived($review))->afterCommit());
        } catch (Throwable $e) {
            Log::error('No se pudo encolar el aviso de reseña para la tienda.', [
                'review_id' => $review->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /** Keeps products.average_rating / reviews_count in step with the published reviews. */
    public function saved(ProductReview $review): void
    {
        if ($review->wasRecentlyCreated || $review->wasChanged(['rating', 'status', 'product_id'])) {
            $this->refreshProductStats($review->product_id);
        }
    }

    public function deleted(ProductReview $review): void
    {
        $this->refreshProductStats($review->product_id);
    }

    private function refreshProductStats(?int $productId): void
    {
        if (!$productId) {
            return;
        }

        $stats = ProductReview::published()
            ->where('product_id', $productId)
            ->selectRaw('COUNT(*) as reviews_count, AVG(rating) as average_rating')
            ->first();

        // toBase(): a review must never bump the product's updated_at or touch other columns.
        Product::withTrashed()->whereKey($productId)->toBase()->update([
            'reviews_count'  => (int) ($stats->reviews_count ?? 0),
            'average_rating' => round((float) ($stats->average_rating ?? 0), 2),
        ]);
    }
}
