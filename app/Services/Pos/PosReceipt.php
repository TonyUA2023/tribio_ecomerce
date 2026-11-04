<?php

namespace App\Services\Pos;

use App\Models\Order;

/**
 * What a counter sale's ticket says, shared by the web register (WhatsApp text) and the
 * mobile quick sale (ERP Fase 4e): lines with packaging, totals, payments, change and,
 * for credit sales, what is still owed and when.
 */
class PosReceipt
{
    public function __construct(private PosSchema $schema)
    {
    }

    /** The ticket as data (the mobile app draws it; amounts in PEN). */
    public function data(Order $order): array
    {
        $order->loadMissing(['items', 'payments', 'store']);
        $installments = $this->schema->creditReady() && $order->due_date ? $order->installments()->get() : collect();

        return [
            'id'            => $order->id,
            'order_number'  => $order->order_number,
            'document_type'   => $order->document_type ?? SalesDocuments::NOTA,
            'document_label'  => $order->document_label,
            'document_number' => $order->document_code,
            'store'         => $order->store?->name,
            'created_at'    => $order->created_at?->toIso8601String(),
            'customer_name' => $order->customer_name,
            'status'        => $order->status,
            'lines'         => $order->items->map(fn ($item) => [
                'name'     => $item->product_name . ($item->variant_title ? ' · ' . $item->variant_title : ''),
                'quantity' => $item->unit_name ? (int) $item->unit_quantity : (int) $item->quantity,
                'unit'     => $item->unit_name,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
            'subtotal'      => (float) $order->subtotal,
            'discount'      => (float) $order->discount,
            'total'         => (float) $order->total,
            'payments'      => $order->payments->where('status', 'paid')->map(fn ($payment) => [
                'method' => $payment->method,
                'label'  => PosSaleService::METHODS[$payment->method] ?? 'Pago',
                'amount' => (float) $payment->amount,
            ])->values()->all(),
            'cash_tendered' => $order->cash_tendered !== null ? (float) $order->cash_tendered : null,
            'change'        => (float) ($order->change_given ?? 0),
            'owed'          => (float) $order->balance_due,
            'installments'  => $installments->map(fn ($i) => ['number' => (int) $i->number, 'due_date' => $i->due_date->toDateString(), 'amount' => (float) $i->amount])->values()->all(),
            'whatsapp_url'  => $order->customer_phone ? 'https://wa.me/' . $order->customer_phone . '?text=' . rawurlencode($this->text($order)) : null,
        ];
    }

    /** The ticket as a WhatsApp message. */
    public function text(Order $order): string
    {
        $order->loadMissing(['items', 'store']);
        $lines = $order->items->map(fn ($item) => ($item->unit_name ? "{$item->unit_quantity} {$item->unit_name}" : $item->quantity) . " × {$item->product_name}"
            . ($item->variant_title ? " ({$item->variant_title})" : '') . ' — S/ ' . number_format((float) $item->subtotal, 2));

        return "¡Gracias por tu compra en {$order->store->name}! 🧾\n{$order->document_label} " . ($order->document_code ?? $order->order_number) . "\n" . $lines->implode("\n")
            . ((float) $order->discount > 0 ? "\nDescuento: −S/ " . number_format((float) $order->discount, 2) : '')
            . "\nTotal: S/ " . number_format((float) $order->total, 2)
            . ((float) $order->balance_due > 0 && $this->schema->creditReady() && $order->due_date
                ? "\nQueda debiendo: S/ " . number_format((float) $order->balance_due, 2) . ' — '
                    . $order->installments()->get()->map(fn ($i) => 'S/ ' . number_format((float) $i->amount, 2) . ' el ' . $i->due_date->format('d/m'))->implode(', ')
                : '');
    }
}
