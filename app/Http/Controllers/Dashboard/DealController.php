<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveDealRequest;
use App\Models\Deal;
use App\Services\Crm\CrmSchema;
use App\Services\Crm\DealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Dashboard → Clientes → Oportunidades: the sales pipeline (DealService). */
class DealController extends Controller
{
    public function __construct(private CrmSchema $schema)
    {
    }

    public function index(Request $request, DealService $deals)
    {
        abort_unless($this->schema->dealsReady(), 404);
        $store = Auth::user()->currentStore();

        $open = Deal::where('store_id', $store->id)->open()->with('contact:id,name,phone')
            ->orderByRaw('CASE WHEN expected_close_at IS NULL THEN 1 ELSE 0 END')->orderBy('expected_close_at')->orderByDesc('id')->get();

        return view('dashboard.contacts.deals', [
            'columns'  => collect(Deal::OPEN_STAGES)->map(fn ($label, $stage) => $open->where('stage', $stage)->values()),
            'summary'  => $deals->summary($store),
            'closed'   => Deal::where('store_id', $store->id)->whereIn('stage', ['ganada', 'perdida'])
                ->with('contact:id,name')->latest('closed_at')->limit(10)->get(),
            'contacts' => $store->contacts()->orderBy('name')->limit(500)->get(['id', 'name']),
        ]);
    }

    public function store(SaveDealRequest $request, DealService $deals): RedirectResponse
    {
        abort_unless($this->schema->dealsReady(), 404);
        $contact = $request->user()->currentStore()->contacts()->findOrFail($request->integer('contact_id'));
        $deals->create($contact, $request->validated(), $request->user()->id);

        return back()->with('success', 'Creamos la oportunidad.');
    }

    public function update(SaveDealRequest $request, DealService $deals, int $deal): RedirectResponse
    {
        $deals->update($this->owned($deal), $request->validated());

        return back()->with('success', 'Guardamos la oportunidad.');
    }

    public function move(Request $request, DealService $deals, int $deal): RedirectResponse
    {
        $request->validate(['stage' => ['required', Rule::in(array_keys(Deal::OPEN_STAGES))]]);
        $deal = $this->owned($deal);
        $deals->move($deal, $request->input('stage'), $request->user()->id);

        return back()->with('success', "«{$deal->title}» pasó a {$deal->fresh()->stage_label}.");
    }

    public function win(Request $request, DealService $deals, int $deal): RedirectResponse
    {
        $request->validate(['order_id' => ['nullable', 'integer']]);
        $deal = $this->owned($deal);
        $deals->win($deal, $request->filled('order_id') ? $request->integer('order_id') : null, $request->user()->id);

        return back()->with('success', "🏆 ¡Ganaste «{$deal->title}»!");
    }

    public function lose(Request $request, DealService $deals, int $deal): RedirectResponse
    {
        $request->validate([
            'lost_reason' => ['required', Rule::in(array_keys(Deal::LOST_REASONS))],
            'lost_note'   => ['nullable', 'string', 'max:255'],
        ], ['lost_reason.required' => 'Elige por qué se perdió: sirve para mejorar.']);
        $deal = $this->owned($deal);
        $deals->lose($deal, $request->input('lost_reason'), $request->input('lost_note'), $request->user()->id);

        return back()->with('success', "Anotamos que se perdió «{$deal->title}».");
    }

    public function reopen(DealService $deals, int $deal): RedirectResponse
    {
        $deal = $this->owned($deal);
        $deals->reopen($deal, Auth::id());

        return back()->with('success', "Reabrimos «{$deal->title}».");
    }

    public function destroy(int $deal): RedirectResponse
    {
        $this->owned($deal)->delete();

        return back()->with('success', 'Eliminamos la oportunidad.');
    }

    private function owned(int $id): Deal
    {
        abort_unless($this->schema->dealsReady(), 404);

        return Deal::where('store_id', Auth::user()->currentStore()->id)->with('contact')->findOrFail($id);
    }
}
