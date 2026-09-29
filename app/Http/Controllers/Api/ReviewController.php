<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReplyToReviewRequest;
use App\Http\Resources\Mobile\ReviewResource;
use App\Models\ProductReview;
use App\Services\Reviews\ProductReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Reseñas: what customers said about the owner's products; hide one, answer publicly. */
class ReviewController extends Controller
{
    use ResolvesCurrentStore;

    public function __construct(private ProductReviewService $reviews)
    {
    }

    /** A review of another store answers 404 — its existence is never confirmed. */
    private function owned(int $id): ProductReview
    {
        return ProductReview::where('store_id', $this->currentStore()->id)->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $store = $this->currentStore();

        // The deploy doesn't run migrations: until the tables exist, say so instead of a 500.
        if (!$this->reviews->isAvailable()) {
            return response()->json([
                'available' => false,
                'message'   => 'Las reseñas se están activando en tu cuenta. Inténtalo de nuevo en unos minutos.',
                'data'      => [],
                'stats'     => ['total' => 0, 'published' => 0, 'hidden' => 0, 'unanswered' => 0, 'average' => null],
            ]);
        }

        $rating = $request->integer('rating');
        $status = in_array($request->query('status'), ['published', 'hidden', 'unanswered'], true) ? $request->query('status') : null;

        $reviews = ProductReview::where('store_id', $store->id)
            ->with('product:id,name,slug,image_path,deleted_at')
            ->when($rating >= 1 && $rating <= 5, fn ($q) => $q->where('rating', $rating))
            ->when($request->integer('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($status === 'published', fn ($q) => $q->published())
            ->when($status === 'hidden', fn ($q) => $q->where('status', ProductReview::STATUS_HIDDEN))
            ->when($status === 'unanswered', fn ($q) => $q->published()->whereNull('store_reply'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(12);

        // One grouped query for the whole header instead of a COUNT per chip.
        $t = ProductReview::where('store_id', $store->id)
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN status = 'hidden' THEN 1 ELSE 0 END) as hidden,
                SUM(CASE WHEN status = 'published' AND store_reply IS NULL THEN 1 ELSE 0 END) as unanswered,
                AVG(CASE WHEN status = 'published' THEN rating END) as average")
            ->first();

        return response()->json([
            'available' => true,
            'data'      => ReviewResource::collection($reviews)->resolve(),
            'meta'      => ['current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(), 'total' => $reviews->total()],
            'stats'     => [
                'total'      => (int) $t->total,
                'published'  => (int) $t->published,
                'hidden'     => (int) $t->hidden,
                'unanswered' => (int) $t->unanswered,
                'average'    => $t->average !== null ? round((float) $t->average, 1) : null,
            ],
        ]);
    }

    public function toggleVisibility(int $id): JsonResponse
    {
        $review = $this->owned($id);
        $publish = !$review->isPublished();
        $this->reviews->setVisibility($review, $publish);

        return response()->json([
            'message' => $publish
                ? 'La reseña volvió a ser visible en tu tienda.'
                : 'Ocultamos la reseña: ya no aparece en tu tienda ni cuenta en la calificación.',
            'review'  => new ReviewResource($review->refresh()),
        ]);
    }

    public function reply(ReplyToReviewRequest $request, int $id): JsonResponse
    {
        $review = $this->reviews->reply($this->owned($id), $request->validated('reply'));

        return response()->json([
            'message' => 'Publicamos tu respuesta: los visitantes la verán debajo de la reseña.',
            'review'  => new ReviewResource($review->refresh()),
        ]);
    }

    public function destroyReply(int $id): JsonResponse
    {
        $review = $this->reviews->removeReply($this->owned($id));

        return response()->json([
            'message' => 'Quitamos tu respuesta de la reseña.',
            'review'  => new ReviewResource($review->refresh()),
        ]);
    }
}
