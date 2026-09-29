<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mensajes: what buyers send from the storefront — contact form, Libro de Reclamaciones and
 * newsletter sign-ups. Same filters and read rules as the web inbox.
 */
class MessageController extends Controller
{
    use ResolvesCurrentStore;

    private const FILTERS = ['sin-leer', ContactMessage::KIND_COMPLAINT, ContactMessage::KIND_MESSAGE, ContactMessage::KIND_SUBSCRIPTION];

    public function index(Request $request): JsonResponse
    {
        $store = $this->currentStore();
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : null;

        $messages = $store->contactMessages()
            ->when($filter === 'sin-leer', fn ($q) => $q->where('is_read', false))
            ->when($filter && $filter !== 'sin-leer', fn ($q) => $q->ofKind($filter))
            ->latest('id')
            ->paginate(20);

        $base = $store->contactMessages();
        $counts = [
            'total'                           => (clone $base)->count(),
            'sin-leer'                        => (clone $base)->where('is_read', false)->count(),
            ContactMessage::KIND_COMPLAINT    => (clone $base)->ofKind(ContactMessage::KIND_COMPLAINT)->count(),
            ContactMessage::KIND_MESSAGE      => (clone $base)->ofKind(ContactMessage::KIND_MESSAGE)->count(),
            ContactMessage::KIND_SUBSCRIPTION => (clone $base)->ofKind(ContactMessage::KIND_SUBSCRIPTION)->count(),
        ];

        return response()->json([
            'data'   => $messages->getCollection()->map(fn (ContactMessage $m) => $this->present($m, false))->values(),
            'meta'   => ['current_page' => $messages->currentPage(), 'last_page' => $messages->lastPage(), 'total' => $messages->total()],
            'counts' => $counts,
        ]);
    }

    /** Opening a message marks it as read, like the web. */
    public function show(int $id): JsonResponse
    {
        $message = $this->currentStore()->contactMessages()->findOrFail($id);
        if (!$message->is_read) {
            $message->update(['is_read' => true]);
        }

        return response()->json(['message' => $this->present($message, true)]);
    }

    public function markUnread(int $id): JsonResponse
    {
        $message = $this->currentStore()->contactMessages()->findOrFail($id);
        $message->update(['is_read' => false]);

        return response()->json(['message' => 'Marcado como no leído.', 'item' => $this->present($message, false)]);
    }

    private function present(ContactMessage $m, bool $full): array
    {
        return [
            'id'         => $m->id,
            'kind'       => $m->kind,
            'name'       => $m->name,
            'email'      => $m->email,
            'phone'      => $m->phone,
            'subject'    => $m->subject,
            'preview'    => mb_strimwidth((string) $m->message, 0, 140, '…'),
            'body'       => $full ? $m->message : null,
            'is_read'    => (bool) $m->is_read,
            'created_at' => $m->created_at?->toISOString(),
        ];
    }
}
