<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CollectDebtRequest;
use App\Http\Requests\Dashboard\SaveCreditLimitRequest;
use App\Models\Contact;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\RegisterSession;
use App\Services\Crm\MessageTemplates;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosSchema;
use App\Services\Pos\ReceivableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dashboard → Por cobrar: credit sales owed, aging, collections and credit limits (ERP Fase 4b). */
class ReceivableController extends Controller
{
    public function __construct(private PosSchema $schema)
    {
    }

    public function index(Request $request, ReceivableService $receivables)
    {
        abort_unless($this->schema->creditReady(), 404);
        $store = $request->user()->currentStore();
        $customers = $receivables->customers($store);
        $filter = in_array($request->query('ver'), ['vencidos', 'semana'], true) ? $request->query('ver') : null;
        $search = mb_strtolower(trim((string) $request->query('q')));
        $weekEnd = $receivables->today()->addDays(7);

        return view('dashboard.receivables.index', [
            'summary'   => $receivables->summary($store, $customers),
            'customers' => $customers
                ->when($filter === 'vencidos', fn ($rows) => $rows->where('overdue', '>', 0))
                ->when($filter === 'semana', fn ($rows) => $rows->filter(fn ($row) => $row['next_due'] && $row['next_due']->lte($weekEnd)))
                ->when($search !== '', fn ($rows) => $rows->filter(fn ($row) => str_contains(mb_strtolower($row['contact']->name), $search)
                    || (strlen(preg_replace('/\D+/', '', $search)) >= 4 && str_contains((string) $row['contact']->phone, preg_replace('/\D+/', '', $search)))))
                ->values(),
            'filter'    => $filter,
            'aging'     => ReceivableService::AGING,
        ]);
    }

    public function show(Request $request, ReceivableService $receivables, MessageTemplates $templates, int $contact)
    {
        $contact = $this->contact($request, $contact);
        $store = $request->user()->currentStore();
        $open = $receivables->openOrders($store->id, $contact->id)->with(['installments', 'items'])->get()
            ->map(fn (Order $order) => ['order' => $order, 'schedule' => $receivables->schedule($order)]);
        $oldest = $open->sortBy(fn ($row) => $row['schedule']->firstWhere('pending', '>', 0)['due_date'] ?? now())->first()['order'] ?? null;
        $reminder = $templates->find($store, 'payment');

        return view('dashboard.receivables.show', [
            'contact'   => $contact,
            'open'      => $open,
            'owed'      => round((float) $open->sum(fn ($row) => (float) $row['order']->balance_due), 2),
            'overdue'   => round((float) $open->sum(fn ($row) => $row['schedule']->where('status', 'overdue')->sum('pending')), 2),
            'payments'  => OrderPayment::whereHas('order', fn ($q) => $q->where('store_id', $store->id)->where('contact_id', $contact->id)->whereNotNull('due_date'))
                ->where('status', OrderPayment::STATUS_PAID)->with('order:id,order_number')->latest('paid_at')->limit(30)->get(),
            'sessions'  => RegisterSession::where('store_id', $store->id)->where('status', 'open')->with('register:id,name')->get(),
            'methods'   => PosSaleService::METHODS,
            'reminder'  => $oldest && $reminder && $contact->phone
                ? $templates->whatsappUrl($contact, $templates->render($reminder['body'], $contact, null, $oldest))
                : null,
        ]);
    }

    public function collect(CollectDebtRequest $request, ReceivableService $receivables, int $contact): RedirectResponse
    {
        $contact = $this->contact($request, $contact);
        $data = $request->validated();
        $session = !empty($data['register_session_id'])
            ? RegisterSession::where('store_id', $contact->store_id)->findOrFail($data['register_session_id'])
            : null;
        $actor = app(\App\Services\Pos\PosAccess::class)->authorize($request->user()->currentStore(), null);
        $result = $receivables->collect($contact, (float) $data['amount'], $data['method'], $session, $request->user()->id, $data['uuid'], $data['order_id'] ?? null, $actor);

        return redirect()->route('dashboard.receivables.show', $contact->id)->with('success', $result['duplicate']
            ? 'Ese cobro ya estaba registrado.'
            : 'Cobraste S/ ' . number_format((float) $data['amount'], 2) . '. ' . ($result['owed'] > 0 ? 'Aún debe S/ ' . number_format($result['owed'], 2) . '.' : 'Ya no debe nada. 🎉'));
    }

    public function limit(SaveCreditLimitRequest $request, int $contact): RedirectResponse
    {
        $contact = $this->contact($request, $contact);
        $limit = $request->validated('credit_limit');
        app(\App\Services\Pos\PosAccess::class)->authorize($request->user()->currentStore(), 'limit', $request->input('approver_pin'));
        $contact->forceFill(['credit_limit' => $limit === null || $limit === '' ? null : round((float) $limit, 2)])->save();

        return back()->with('success', $contact->credit_limit === null ? 'Quitamos el límite de crédito.' : 'Guardamos el límite de crédito.');
    }

    private function contact(Request $request, int $id): Contact
    {
        abort_unless($this->schema->creditReady(), 404);

        return $request->user()->currentStore()->contacts()->findOrFail($id);
    }
}
