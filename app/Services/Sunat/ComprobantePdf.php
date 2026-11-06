<?php

namespace App\Services\Sunat;

use App\Models\Branch;
use App\Models\ElectronicDocument;
use App\Models\Order;
use App\Models\Store;
use App\Services\Pos\SalesDocuments;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;

/** The "representación impresa" of an electronic boleta or factura: A4 with the QR SUNAT asks for. */
class ComprobantePdf
{
    public function data(ElectronicDocument $doc): array
    {
        $order = Order::with(['items', 'installments'])->findOrFail($doc->order_id);
        $store = Store::findOrFail($doc->store_id);
        $branch = ($order->branch_id ? Branch::find($order->branch_id) : null) ?? Branch::where('store_id', $store->id)->where('is_main', true)->first();
        $total = round((float) $order->total, 2);
        $split = SalesDocuments::breakdown($total);

        return [
            'doc'     => $doc,
            'order'   => $order,
            'store'   => $store,
            'branch'  => $branch,
            'at'      => $order->created_at->copy()->setTimezone('America/Lima'),
            'taxable' => $split['taxable'],
            'igv'     => $split['igv'],
            'total'   => $total,
            'words'   => NumberToWords::soles($total),
            'title'   => $doc->type === SalesDocuments::FACTURA ? 'FACTURA ELECTRÓNICA' : 'BOLETA DE VENTA ELECTRÓNICA',
            'qr'      => $this->qr($doc, $order, $store, $split['igv'], $total),
        ];
    }

    public function pdf(ElectronicDocument $doc): string
    {
        return Pdf::loadView('dashboard.pos.electronic-pdf', $this->data($doc))->setPaper('a4')->output();
    }

    /** SUNAT's QR: RUC | tipo | serie | número | IGV | total | fecha | tipo doc cliente | doc cliente | — as an SVG data URI. */
    public function qr(ElectronicDocument $doc, Order $order, Store $store, float $igv, float $total): string
    {
        $document = preg_replace('/\D+/', '', (string) $order->customer_document_number);
        $clientType = strlen($document) === 11 ? '6' : (strlen($document) === 8 ? '1' : '-');
        $text = implode('|', [$store->ruc, InvoiceBuilder::TYPE_CODES[$doc->type] ?? '', $doc->series, $doc->number, number_format($igv, 2, '.', ''),
            number_format($total, 2, '.', ''), $order->created_at->copy()->setTimezone('America/Lima')->format('Y-m-d'), $clientType, $document ?: '-']) . '|';

        $svg = (new Writer(new ImageRenderer(new RendererStyle(160, 0), new SvgImageBackEnd())))->writeString($text);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
