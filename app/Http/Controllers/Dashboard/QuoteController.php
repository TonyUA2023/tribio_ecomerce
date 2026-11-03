<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveQuoteRequest;
use App\Models\Quote;
use App\Models\RegisterSession;
use App\Services\Pos\PosSchema;
use App\Services\Pos\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dashboard → Caja → Proformas: quotes saved at the register, printed, sent and charged later (ERP Fase 4c). */
class QuoteController extends Controller
{
    public function __construct(private PosSchema $schema)
    {
    }

    public function index(Request $request)
    {
        abort_unless($this->schema->extrasReady(), 404);
        $store = $request->user()->currentStore();
        $filter = in_array($request->query('ver'), ['vigentes', 'vendidas'], true) ? $request->query('ver') : null;
        $search = trim((string) $request->query('q'));

        return view('dashboard.quotes.index', [
            'quotes'  => Quote::where('store_id', $store->id)
                ->when($filter === 'vigentes', fn ($q) => $q->where('status', 'open')->where('valid_until', '>=', now('America/Lima')->toDateString()))
                ->when($filter === 'vendidas', fn ($q) => $q->where('status', 'sold'))
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")))
                ->latest('id')->paginate(25)->withQueryString(),
            'filter'  => $filter,
            'canSell' => RegisterSession::where('store_id', $store->id)->where('status', 'open')->exists(),
        ]);
    }

    public function store(SaveQuoteRequest $request, QuoteService $quotes): JsonResponse
    {
        abort_unless($this->schema->extrasReady(), 404);
        $quote = $quotes->create($request->user()->currentStore(), $request->validated(), $request->user()->id);

        return response()->json([
            'quote_id'     => $quote->id,
            'number'       => $quote->number,
            'total'        => (float) $quote->total,
            'valid_until'  => $quote->valid_until->format('d/m/Y'),
            'print_url'    => route('dashboard.quotes.show', $quote->id),
            'whatsapp_url' => $quote->customer_phone ? 'https://wa.me/' . $quote->customer_phone . '?text=' . rawurlencode($this->text($quote)) : null,
        ], 201);
    }

    /** Printable quote (A4 or phone); `?imprimir=1` prints on load. */
    public function show(Request $request, int $quote)
    {
        $quote = $this->owned($request, $quote)->load('store', 'user:id,name');

        return view('dashboard.quotes.show', ['quote' => $quote, 'print' => $request->boolean('imprimir'),
            'whatsapp' => $quote->customer_phone ? 'https://wa.me/' . $quote->customer_phone . '?text=' . rawurlencode($this->text($quote)) : null]);
    }

    public function cancel(Request $request, QuoteService $quotes, int $quote): RedirectResponse
    {
        $quote = $this->owned($request, $quote);
        app(\App\Services\Pos\PosAccess::class)->authorize($request->user()->currentStore(), 'cancel_quote', $request->input('approver_pin'));
        $quotes->cancel($quote);

        return back()->with('success', 'Anulamos la proforma.');
    }

    private function text(Quote $quote): string
    {
        $lines = collect($quote->items)->map(fn ($item) => "{$item['quantity']}" . ($item['unit_name'] ? " {$item['unit_name']}" : '') . " × {$item['name']} — S/ " . number_format((float) $item['subtotal'], 2));

        return "Proforma {$quote->number} de {$quote->store->name}\n" . $lines->implode("\n")
            . ((float) $quote->discount > 0 ? "\nDescuento: −S/ " . number_format((float) $quote->discount, 2) : '')
            . "\nTotal: S/ " . number_format((float) $quote->total, 2) . "\nVálida hasta el " . $quote->valid_until->format('d/m/Y') . '.';
    }

    private function owned(Request $request, int $id): Quote
    {
        abort_unless($this->schema->extrasReady(), 404);

        return Quote::where('store_id', $request->user()->currentStore()->id)->findOrFail($id);
    }
}
