<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Dashboard "Mensajes": what buyers send from the storefront — contact form, the virtual
 * Libro de Reclamaciones and newsletter sign-ups. Always scoped to the current store.
 */
class MessageController extends Controller
{
    public const FILTERS = ['sin-leer', ContactMessage::KIND_COMPLAINT, ContactMessage::KIND_MESSAGE, ContactMessage::KIND_SUBSCRIPTION];

    public function index(Request $request): View
    {
        $store = Auth::user()->currentStore();
        $filter = in_array($request->query('filtro'), self::FILTERS, true) ? $request->query('filtro') : null;

        $messages = $store->contactMessages()
            ->when($filter === 'sin-leer', fn ($q) => $q->where('is_read', false))
            ->when($filter && $filter !== 'sin-leer', fn ($q) => $q->ofKind($filter))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $base = $store->contactMessages();
        $counts = [
            'total' => (clone $base)->count(),
            'sin-leer' => (clone $base)->where('is_read', false)->count(),
            ContactMessage::KIND_COMPLAINT => (clone $base)->ofKind(ContactMessage::KIND_COMPLAINT)->count(),
            ContactMessage::KIND_MESSAGE => (clone $base)->ofKind(ContactMessage::KIND_MESSAGE)->count(),
            ContactMessage::KIND_SUBSCRIPTION => (clone $base)->ofKind(ContactMessage::KIND_SUBSCRIPTION)->count(),
        ];

        return view('dashboard.messages.index', compact('store', 'messages', 'filter', 'counts'));
    }

    public function show(int $message): View
    {
        $store = Auth::user()->currentStore();
        $contactMessage = $store->contactMessages()->findOrFail($message);
        if (!$contactMessage->is_read) {
            $contactMessage->update(['is_read' => true]);
        }

        return view('dashboard.messages.show', ['store' => $store, 'message' => $contactMessage]);
    }

    public function markUnread(int $message): RedirectResponse
    {
        $store = Auth::user()->currentStore();
        $store->contactMessages()->findOrFail($message)->update(['is_read' => false]);

        return redirect()->route('dashboard.mensajes.index')->with('success', 'Marcado como no leído.');
    }
}
