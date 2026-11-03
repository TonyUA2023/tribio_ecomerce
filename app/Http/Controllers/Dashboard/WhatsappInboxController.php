<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\Whatsapp\InboxService;
use App\Services\Whatsapp\TemplateService;
use App\Services\Whatsapp\WhatsappSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Dashboard → WhatsApp: the shared inbox of the store's Cloud API number. */
class WhatsappInboxController extends Controller
{
    public function __construct(private WhatsappSchema $schema)
    {
    }

    public function index(Request $request)
    {
        $store = $request->user()->currentStore();
        if (!$this->schema->ready()) {
            return view('dashboard.whatsapp.index', ['ready' => false, 'connection' => null, 'conversations' => collect(), 'filter' => null]);
        }

        $filter = $request->query('ver') === 'sin-leer' ? 'sin-leer' : null;
        $search = trim((string) $request->query('q'));
        $digits = preg_replace('/\D+/', '', $search);

        return view('dashboard.whatsapp.index', [
            'ready'         => true,
            'connection'    => $store->whatsappConnection()->first(),
            'filter'        => $filter,
            'conversations' => WhatsappConversation::where('store_id', $store->id)->with('contact:id,name,lifecycle_stage')
                ->when($filter, fn ($q) => $q->where('unread_count', '>', 0))
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('profile_name', 'like', "%{$search}%")
                    ->when(strlen($digits) >= 3, fn ($q) => $q->orWhere('wa_id', 'like', "%{$digits}%"))
                    ->orWhereHas('contact', fn ($q) => $q->where('name', 'like', "%{$search}%"))))
                ->orderByDesc('last_message_at')->paginate(30)->withQueryString(),
        ]);
    }

    public function show(Request $request, InboxService $inbox, TemplateService $templates, int $conversation)
    {
        $conversation = $this->owned($request, $conversation);
        $inbox->markRead($conversation);

        return view('dashboard.whatsapp.show', [
            'conversation' => $conversation,
            'messages'     => $conversation->messages()->latest('id')->limit(100)->get()->reverse()->values(),
            'templates'    => $this->schema->templatesReady() ? $templates->forConversation($conversation) : null,
        ]);
    }

    /** Opens (or creates) the chat with a customer of the store, to write first with a template. */
    public function start(Request $request, InboxService $inbox, int $contact): RedirectResponse
    {
        abort_unless($this->schema->ready(), 404);
        $store = $request->user()->currentStore();
        $contact = $store->contacts()->findOrFail($contact);
        $connection = $store->whatsappConnection()->first();

        if (!$connection?->isConnected()) {
            return back()->with('error', 'Primero conecta tu número de WhatsApp.');
        }
        if (!$contact->phone) {
            return back()->with('error', "{$contact->name} no tiene celular registrado.");
        }

        return redirect()->route('dashboard.whatsapp.show', $inbox->conversationFor($connection, $contact)->id);
    }

    public function sendTemplate(Request $request, InboxService $inbox, int $conversation): RedirectResponse
    {
        abort_unless($this->schema->templatesReady(), 404);
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'values'      => ['nullable', 'array', 'max:20'],
            'values.*'    => ['nullable', 'string', 'max:1000'],
        ], ['template_id.required' => 'Elige una plantilla aprobada.']);
        $conversation = $this->owned($request, $conversation);
        $template = WhatsappTemplate::where('connection_id', $conversation->connection_id)->find($data['template_id']);
        if (!$template) {
            return back()->withErrors(['template_id' => 'Elige una plantilla aprobada.']);
        }

        $inbox->sendTemplate($conversation, $template, $data['values'] ?? [], $request->user()->id);

        return redirect()->route('dashboard.whatsapp.show', $conversation->id);
    }

    /** A customer's photo, audio, video or document. Only plain media types open in the browser. */
    public function media(Request $request, int $message)
    {
        abort_unless($this->schema->ready(), 404);
        $message = WhatsappMessage::where('store_id', $request->user()->currentStore()->id)->findOrFail($message);
        $path = $message->payload['path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $mime = (string) ($message->payload['mime_type'] ?? 'application/octet-stream');
        $inline = preg_match('#^(image/(jpeg|png|webp)|audio/(ogg|mpeg|mp4|aac)|video/mp4)$#', $mime) === 1;
        $headers = ['Content-Type' => $inline ? $mime : 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=86400'];
        $name = $message->payload['filename'] ?? basename($path);

        return $inline
            ? response()->file(Storage::disk('local')->path($path), $headers)
            : response()->download(Storage::disk('local')->path($path), $name, $headers);
    }

    public function reply(Request $request, InboxService $inbox, int $conversation): RedirectResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:4096']], ['body.required' => 'Escribe el mensaje.']);
        $inbox->reply($this->owned($request, $conversation), trim($request->input('body')), $request->user()->id);

        return redirect()->route('dashboard.whatsapp.show', $conversation);
    }

    /** New messages after `after` and the current state of the last outgoing ones (polled by the chat). */
    public function poll(Request $request, InboxService $inbox, int $conversation): JsonResponse
    {
        $conversation = $this->owned($request, $conversation);
        $new = $conversation->messages()->where('id', '>', $request->integer('after'))->orderBy('id')->limit(50)->get();
        if ($new->contains(fn ($m) => $m->isInbound())) {
            $inbox->markRead($conversation);
        }

        return response()->json([
            'html'        => $new->map(fn ($message) => view('dashboard.whatsapp._message', ['message' => $message])->render())->implode(''),
            'last_id'     => $new->last()?->id ?? $request->integer('after'),
            'statuses'    => $conversation->messages()->where('direction', 'out')->latest('id')->limit(20)->get(['id', 'status', 'error'])
                ->mapWithKeys(fn ($m) => [$m->id => ['label' => $m->status_label, 'failed' => $m->status === 'failed', 'error' => $m->error]]),
            'window_open' => $conversation->fresh()->windowOpen(),
        ]);
    }

    private function owned(Request $request, int $id): WhatsappConversation
    {
        abort_unless($this->schema->ready(), 404);

        return WhatsappConversation::where('store_id', $request->user()->currentStore()->id)->with(['contact', 'connection'])->findOrFail($id);
    }
}
