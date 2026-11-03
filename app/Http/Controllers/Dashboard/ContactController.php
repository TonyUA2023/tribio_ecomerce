<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveContactRequest;
use App\Http\Requests\Dashboard\StoreContactEventRequest;
use App\Models\Contact;
use App\Models\ContactTask;
use App\Services\Crm\ContactService;
use App\Services\Crm\CrmSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/** Dashboard → Clientes: the store's contacts, built from its orders (see ContactResolver). */
class ContactController extends Controller
{
    public const SEGMENTS = [
        'recurrentes' => 'Recurrentes (2+ pedidos)',
        'una_compra'  => 'Un pedido',
        'inactivos'   => 'Sin comprar hace 90+ días',
    ];

    public function index(Request $request, CrmSchema $schema)
    {
        $store = Auth::user()->currentStore();
        if (!$schema->ready()) {
            return view('dashboard.contacts.index', ['store' => $store, 'ready' => false]);
        }

        $segment = array_key_exists($request->query('segmento'), self::SEGMENTS) ? $request->query('segmento') : null;
        $sort = $request->query('orden') === 'gasto' ? 'gasto' : 'recientes';
        $profile = $schema->profileReady();
        $stage = $profile && array_key_exists($request->query('etapa'), Contact::STAGES) ? $request->query('etapa') : null;
        $tag = $profile ? mb_strtolower(trim((string) $request->query('etiqueta'))) : '';

        $contacts = $store->contacts()
            ->search($request->query('buscar'))
            ->when($stage, fn ($q) => $q->where('lifecycle_stage', $stage))
            ->when($tag !== '', fn ($q) => $q->whereJsonContains('tags', $tag))
            ->inSegment($segment)
            ->when($sort === 'gasto', fn ($q) => $q->orderByDesc('total_spent'), fn ($q) => $q->orderByDesc('last_order_at'))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.contacts.index', [
            'store'    => $store,
            'ready'    => true,
            'contacts' => $contacts,
            'segment'  => $segment,
            'sort'     => $sort,
            'search'   => $request->query('buscar'),
            'total'    => $store->contacts()->count(),
            'profile'  => $profile,
            'stage'    => $stage,
            'tag'      => $tag,
            'stageCounts' => $profile ? $store->contacts()->selectRaw('lifecycle_stage, COUNT(*) as total')->groupBy('lifecycle_stage')->pluck('total', 'lifecycle_stage') : collect(),
            'dueTasks' => $profile ? ContactTask::where('store_id', $store->id)->pending()->where('due_at', '<=', now()->endOfDay())->count() : 0,
        ]);
    }

    public function create(CrmSchema $schema)
    {
        abort_unless($schema->profileReady(), 404);

        return view('dashboard.contacts.create', ['contact' => new Contact()]);
    }

    public function store(SaveContactRequest $request, ContactService $service, CrmSchema $schema): RedirectResponse
    {
        abort_unless($schema->profileReady(), 404);
        $contact = $service->create($request->user()->currentStore(), $request->validated(), $request->user()->id);

        return redirect()->route('dashboard.clientes.show', $contact->id)->with('success', "Agregamos a {$contact->name}.");
    }

    public function update(SaveContactRequest $request, ContactService $service, CrmSchema $schema, int $contact): RedirectResponse
    {
        abort_unless($schema->profileReady(), 404);
        $contact = $service->update($request->user()->currentStore()->contacts()->findOrFail($contact), $request->validated());

        return redirect()->route('dashboard.clientes.show', $contact->id)->with('success', 'Guardamos los datos del cliente.');
    }

    public function logEvent(StoreContactEventRequest $request, ContactService $service, CrmSchema $schema, int $contact): RedirectResponse
    {
        abort_unless($schema->profileReady(), 404);
        $contact = $request->user()->currentStore()->contacts()->findOrFail($contact);
        $service->log($contact, $request->input('type'), $request->input('body'), $request->user()->id,
            $request->filled('occurred_at') ? Carbon::parse($request->input('occurred_at')) : null);

        return back()->with('success', 'Lo anotamos en su historia.');
    }

    public function show(CrmSchema $schema, int $contact)
    {
        abort_unless($schema->ready(), 404);
        $store = Auth::user()->currentStore();
        $contact = $store->contacts()->findOrFail($contact);
        $orders = $contact->orders()->latest()->limit(50)->get();
        $profile = $schema->profileReady();

        // One timeline: orders + what was logged by hand or by the system, newest first.
        $timeline = $orders->map(fn ($order) => ['kind' => 'order', 'at' => $order->created_at, 'order' => $order]);
        if ($profile) {
            $timeline = $timeline->concat($contact->events()->with('user:id,name')->latest('occurred_at')->limit(100)->get()
                ->map(fn ($event) => ['kind' => 'event', 'at' => $event->occurred_at, 'event' => $event]));
        }

        return view('dashboard.contacts.show', [
            'store'    => $store,
            'contact'  => $contact,
            'orders'   => $orders,
            'profile'  => $profile,
            'timeline' => $timeline->sortByDesc('at')->values(),
            'tasks'    => $profile ? $contact->tasks()->pending()->with('product:id,name')->orderBy('due_at')->get() : collect(),
            'templates' => $profile ? app(\App\Services\Crm\MessageTemplates::class)->all($store) : collect(),
            'hasBalance' => $profile && app(\App\Services\Crm\MessageTemplates::class)->hasBalance($contact),
            'chat'       => app(\App\Services\Whatsapp\WhatsappSchema::class)->ready()
                ? $contact->whatsappConversations()->latest('last_message_at')->first()
                : null,
            'apiReady'   => app(\App\Services\Whatsapp\WhatsappSchema::class)->ready()
                && (bool) $store->whatsappConnection()->where('status', 'connected')->exists(),
            'debt'       => app(\App\Services\Pos\PosSchema::class)->creditReady()
                ? app(\App\Services\Pos\ReceivableService::class)->owedBy($contact)
                : null,
            'deals'      => $schema->dealsReady()
                ? $contact->deals()->with('order:id,order_number')->orderByRaw("CASE WHEN stage IN ('ganada', 'perdida') THEN 1 ELSE 0 END")->latest('id')->get()
                : null,
        ]);
    }
}
