<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveWhatsappBroadcastRequest;
use App\Http\Requests\Dashboard\SaveWhatsappBudgetRequest;
use App\Models\Contact;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappConnection;
use App\Models\WhatsappTemplate;
use App\Services\Whatsapp\BroadcastService;
use App\Services\Whatsapp\WhatsappBudget;
use App\Services\Whatsapp\WhatsappSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dashboard → WhatsApp → Difusiones: a template to a segment, with cost, cap and Meta's limit (Fase 3c). */
class WhatsappBroadcastController extends Controller
{
    public function __construct(private WhatsappSchema $schema)
    {
    }

    public function index(Request $request, WhatsappBudget $budget)
    {
        abort_unless($this->schema->broadcastsReady(), 404);
        $store = $request->user()->currentStore();
        $connection = $store->whatsappConnection()->first();

        return view('dashboard.whatsapp.broadcasts.index', [
            'connection' => $connection,
            'budget'     => $connection ? $budget->summary($connection) : null,
            'limit'      => $connection ? $budget->limit($connection) : null,
            'limitUsed'  => $connection ? $budget->limitUsed($connection) : 0,
            'broadcasts' => WhatsappBroadcast::where('store_id', $store->id)->withCount([
                'messages as delivered_count' => fn ($q) => $q->whereIn('status', ['delivered', 'read']),
                'messages as read_count'      => fn ($q) => $q->where('status', 'read'),
            ])->withSum('messages as cost_sum', 'cost')->latest('id')->paginate(20),
        ]);
    }

    public function create(Request $request, BroadcastService $broadcasts, WhatsappBudget $budget)
    {
        $connection = $this->connection($request);
        $store = $request->user()->currentStore();

        return view('dashboard.whatsapp.broadcasts.create', [
            'connection' => $connection,
            'budget'     => $budget->summary($connection),
            'sources'    => $broadcasts->sources(),
            'stages'     => collect(Contact::STAGES)->map(fn ($stage) => $stage['label']),
            'segments'   => ContactController::SEGMENTS,
            'tags'       => $store->contacts()->whereNotNull('tags')->limit(2000)->pluck('tags')->flatten()->filter()->unique()->sort()->take(100)->values(),
            'templates'  => WhatsappTemplate::where('connection_id', $connection->id)->where('status', 'APPROVED')->orderBy('name')->get()
                ->filter->isSendable()
                ->map(fn (WhatsappTemplate $template) => [
                    'id'        => $template->id,
                    'name'      => $template->name,
                    'category'  => $template->category,
                    'label'     => $template->category_label,
                    'rate'      => $connection->rate($template->category),
                    'body'      => $template->body,
                    'variables' => $broadcasts->variables($template),
                ])->values(),
            'preselect'  => $request->integer('plantilla') ?: null,
        ]);
    }

    /** The live estimate on the form: reach, cost against the cap, Meta's limit, a sample. */
    public function estimate(SaveWhatsappBroadcastRequest $request, BroadcastService $broadcasts): JsonResponse
    {
        $connection = $this->connection($request);
        $data = $request->broadcast();
        $template = $this->template($connection, $data['template_id']);

        $estimate = $broadcasts->estimate($request->user()->currentStore(), $connection, $template, $data + ['later' => filled($data['scheduled_at'])]);
        unset($estimate['mapping']);

        return response()->json($estimate);
    }

    public function store(SaveWhatsappBroadcastRequest $request, BroadcastService $broadcasts): RedirectResponse
    {
        $connection = $this->connection($request);
        $data = $request->broadcast();
        $broadcast = $broadcasts->create($request->user()->currentStore(), $connection, $this->template($connection, $data['template_id']), $data, $request->user()->id);

        return redirect()->route('dashboard.whatsapp.broadcasts.show', $broadcast->id)->with('success', $broadcast->scheduled_at->isFuture()
            ? 'Difusión programada para el ' . $broadcast->scheduled_at->copy()->setTimezone(WhatsappBudget::TIMEZONE)->locale('es')->isoFormat('D [de] MMMM, H:mm') . '.'
            : "Estamos enviando «{$broadcast->name}». Los mensajes salen de a poco para cuidar tu número.");
    }

    public function show(Request $request, BroadcastService $broadcasts, int $broadcast)
    {
        $broadcast = $this->owned($request, $broadcast);

        return view('dashboard.whatsapp.broadcasts.show', [
            'broadcast'  => $broadcast,
            'stats'      => $broadcasts->stats($broadcast),
            'recipients' => $broadcast->recipients()->with(['contact:id,name,phone', 'message:id,status,error,conversation_id'])
                ->when($request->query('ver') === 'fallidos', fn ($q) => $q->where(fn ($q) => $q->where('status', 'skipped')
                    ->orWhereHas('message', fn ($q) => $q->where('status', 'failed'))))
                ->orderBy('id')->paginate(50)->withQueryString(),
            'filter'     => $request->query('ver') === 'fallidos' ? 'fallidos' : null,
        ]);
    }

    public function cancel(Request $request, BroadcastService $broadcasts, int $broadcast): RedirectResponse
    {
        $broadcast = $this->owned($request, $broadcast);
        if (!$broadcast->isOpen()) {
            return back()->with('error', 'Esta difusión ya terminó.');
        }
        $broadcasts->cancel($broadcast);

        return back()->with('success', 'Cancelaste la difusión. Los mensajes que ya salieron no se pueden recuperar.');
    }

    public function budget(SaveWhatsappBudgetRequest $request): RedirectResponse
    {
        $connection = $this->connection($request);
        $rates = collect($request->validated('rates') ?? [])->filter(fn ($rate) => $rate !== null && $rate !== '')->map(fn ($rate) => round((float) $rate, 4));
        $connection->forceFill(['monthly_budget' => round((float) $request->validated('monthly_budget'), 2), 'rates' => $rates->isEmpty() ? null : $rates->all()])->save();

        return back()->with('success', 'Guardamos tu tope mensual de WhatsApp.');
    }

    private function connection(Request $request): WhatsappConnection
    {
        abort_unless($this->schema->broadcastsReady(), 404);
        $connection = $request->user()->currentStore()->whatsappConnection()->first();
        abort_unless($connection?->isConnected(), 404);

        return $connection;
    }

    private function template(WhatsappConnection $connection, int $id): WhatsappTemplate
    {
        $template = WhatsappTemplate::where('connection_id', $connection->id)->find($id);
        if (!$template?->isSendable()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['template_id' => 'Elige una plantilla aprobada por Meta.']);
        }

        return $template;
    }

    private function owned(Request $request, int $id): WhatsappBroadcast
    {
        abort_unless($this->schema->broadcastsReady(), 404);

        return WhatsappBroadcast::where('store_id', $request->user()->currentStore()->id)->findOrFail($id);
    }
}
