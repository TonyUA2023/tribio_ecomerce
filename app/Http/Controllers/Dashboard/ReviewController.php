<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReplyToReviewRequest;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Services\Reviews\ProductReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard "Reseñas": what customers said about the owner's products, with the two
 * powers an owner needs — hide a review that shouldn't be public, and answer publicly.
 * Always scoped to Auth::user()->currentStore() (multi-store, see ADR 0002).
 */
class ReviewController extends Controller
{
    public function __construct(private ProductReviewService $reviews)
    {
    }

    private function store(): Store
    {
        $store = Auth::user()->currentStore();
        abort_unless($store, 403, 'Necesitas una tienda para ver reseñas.');

        return $store;
    }

    /** A review of another store answers 404, never 403 — its existence isn't confirmed. */
    private function owned(ProductReview $review): ProductReview
    {
        abort_unless((int) $review->store_id === (int) $this->store()->id, 404);

        return $review;
    }

    public function index(Request $request)
    {
        $store = $this->store();

        // The deploy doesn't run migrations: until this feature's tables exist, say so instead of a 500.
        if (!$this->reviews->isAvailable()) {
            return redirect()->route('dashboard.index')
                ->with('info', 'Las reseñas se están activando en tu cuenta. Inténtalo de nuevo en unos minutos.');
        }

        $rating = $request->integer('calificacion');
        $status = in_array($request->query('estado'), ['published', 'hidden', 'unanswered'], true) ? $request->query('estado') : null;
        $productId = $request->integer('producto');

        $reviews = ProductReview::where('store_id', $store->id)
            ->with('product:id,name,slug,image_path,deleted_at')
            ->when($rating >= 1 && $rating <= 5, fn ($q) => $q->where('rating', $rating))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($status === 'published', fn ($q) => $q->published())
            ->when($status === 'hidden', fn ($q) => $q->where('status', ProductReview::STATUS_HIDDEN))
            ->when($status === 'unanswered', fn ($q) => $q->published()->whereNull('store_reply'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        // One grouped query for the whole header instead of a COUNT per chip.
        $totals = ProductReview::where('store_id', $store->id)
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN status = 'hidden' THEN 1 ELSE 0 END) as hidden,
                SUM(CASE WHEN status = 'published' AND store_reply IS NULL THEN 1 ELSE 0 END) as unanswered,
                AVG(CASE WHEN status = 'published' THEN rating END) as average")
            ->first();

        $stats = [
            'total'      => (int) $totals->total,
            'published'  => (int) $totals->published,
            'hidden'     => (int) $totals->hidden,
            'unanswered' => (int) $totals->unanswered,
            'average'    => $totals->average !== null ? round((float) $totals->average, 1) : null,
        ];

        $products = Product::withTrashed()
            ->where('store_id', $store->id)
            ->whereHas('reviews')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('dashboard.reviews.index', compact('store', 'reviews', 'stats', 'products', 'rating', 'status', 'productId'));
    }

    public function toggleVisibility(ProductReview $review)
    {
        $review = $this->owned($review);
        $publish = !$review->isPublished();

        $this->reviews->setVisibility($review, $publish);

        return back()->with('success', $publish
            ? 'La reseña volvió a ser visible en tu tienda.'
            : 'Ocultamos la reseña: ya no aparece en tu tienda ni cuenta en la calificación.');
    }

    public function reply(ReplyToReviewRequest $request, ProductReview $review)
    {
        $this->reviews->reply($this->owned($review), $request->validated('reply'));

        return redirect(route('dashboard.resenas.index', $request->query()) . '#resena-' . $review->id)
            ->with('success', 'Publicamos tu respuesta: los visitantes la verán debajo de la reseña.');
    }

    public function destroyReply(ProductReview $review)
    {
        $this->reviews->removeReply($this->owned($review));

        return back()->with('success', 'Quitamos tu respuesta de la reseña.');
    }
}
