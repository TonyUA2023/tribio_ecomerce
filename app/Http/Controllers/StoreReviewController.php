<?php

namespace App\Http\Controllers;

use App\Helpers\TranslationHelper;
use App\Http\Requests\Reviews\StoreProductReviewRequest;
use App\Models\Product;
use App\Services\Reviews\ProductReviewService;
use App\Services\Reviews\ReviewNotAllowedException;
use App\Services\Storefront\StorefrontStoreResolver;

/**
 * The review form on a storefront product page. A plain POST → redirect back to the
 * reviews section, so it works on every template and custom domain with no JS.
 * Thin on purpose: who may review is decided by ProductReviewService.
 */
class StoreReviewController extends Controller
{
    public function store(StoreProductReviewRequest $request, string $slug, string $product)
    {
        $store = app(StorefrontStoreResolver::class)->resolve($slug);
        $productModel = Product::where('slug', $product)->where('store_id', $store->id)->firstOrFail();

        $reviews = app(ProductReviewService::class);
        $back = strtok(url()->previous(), '#') . '#' . ProductReviewService::PAGE_NAME;
        $user = $request->user();

        if (!$user || !$user->canUseCustomerPortal()) {
            return redirect($back)->withErrors(
                ['review' => $reviews->messageForState(ProductReviewService::STATE_GUEST)],
                'review'
            );
        }

        try {
            $result = $reviews->submit($user, $productModel, (int) $request->validated('rating'), $request->validated('comment'));
        } catch (ReviewNotAllowedException $e) {
            return redirect($back)->withErrors(['review' => $e->getMessage()], 'review')->withInput();
        }

        $en = TranslationHelper::isEn();

        return redirect($back)->with('review_status', $result['created']
            ? ($en ? 'Thank you! Your review is now published.' : '¡Gracias! Tu reseña ya está publicada.')
            : ($en ? 'Done — we updated your review.' : 'Listo, actualizamos tu reseña.'));
    }
}
