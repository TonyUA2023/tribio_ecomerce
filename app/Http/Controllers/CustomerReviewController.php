<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reviews\CustomerReviewRequest;
use App\Models\Product;
use App\Services\Reviews\ProductReviewService;
use App\Services\Reviews\ReviewNotAllowedException;

/**
 * "Calificar mi compra" from the Tribio Pass hub (session) and the mobile app (Sanctum
 * token): one JSON endpoint behind two doors, both resolving the buyer via
 * `$request->user()`. The purchase rule lives in ProductReviewService.
 */
class CustomerReviewController extends Controller
{
    public function store(CustomerReviewRequest $request, ProductReviewService $reviews)
    {
        $user = $request->user();

        if (!$user || !$user->canUseCustomerPortal()) {
            return response()->json(['success' => false, 'message' => 'No autenticado como cliente.'], 403);
        }

        $product = Product::find($request->integer('product_id'));
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'No encontramos ese producto.'], 404);
        }

        try {
            $result = $reviews->submit($user, $product, (int) $request->validated('rating'), $request->validated('comment'));
        } catch (ReviewNotAllowedException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => $e->state], 403);
        }

        $product->refresh();

        return response()->json([
            'success' => true,
            'created' => $result['created'],
            'message' => $result['created'] ? '¡Gracias! Tu reseña ya está publicada.' : 'Listo, actualizamos tu reseña.',
            'review'  => $reviews->transform($result['review']),
            'product' => [
                'id'             => $product->id,
                'average_rating' => (float) $product->average_rating,
                'reviews_count'  => (int) $product->reviews_count,
            ],
        ], $result['created'] ? 201 : 200);
    }
}
