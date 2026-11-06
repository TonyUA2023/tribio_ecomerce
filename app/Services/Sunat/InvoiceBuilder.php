<?php

namespace App\Services\Sunat;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Store;
use App\Services\Pos\SalesDocuments;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Cuota;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Illuminate\Validation\ValidationException;

/**
 * Turns a counter sale (an Order with its numbered boleta/factura) into the UBL 2.1 invoice SUNAT
 * expects. Prices in the POS include IGV, so every line is split into its taxable value and its
 * 18 % and the totals are the sum of the lines (SUNAT checks that they match to the cent).
 *
 * Everything is sold as "gravado" (código 10), in soles, per unit (NIU): the same model the POS reports use.
 */
class InvoiceBuilder
{
    public const TYPE_CODES = [SalesDocuments::FACTURA => '01', SalesDocuments::BOLETA => '03'];

    /** SUNAT catálogo 06 */
    public const DOC_RUC = '6';
    public const DOC_DNI = '1';
    public const DOC_NONE = '-';

    public function build(Order $order, Store $store, Branch $branch): Invoice
    {
        $type = $order->document_type;
        if (!isset(self::TYPE_CODES[$type])) {
            throw ValidationException::withMessages(['document_type' => 'Solo las boletas y facturas se envían a SUNAT.']);
        }

        $lines = $this->lines($order);
        $taxable = round(array_sum(array_column($lines, 'value')), 2);
        $igv = round(array_sum(array_column($lines, 'igv')), 2);
        $total = round(array_sum(array_column($lines, 'gross')), 2);

        $invoice = (new Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101')
            ->setTipoDoc(self::TYPE_CODES[$type])
            ->setSerie((string) $order->document_series)
            ->setCorrelativo((string) $order->document_number)
            ->setFechaEmision(\DateTime::createFromFormat('Y-m-d H:i:s', $order->created_at->copy()->setTimezone('America/Lima')->format('Y-m-d H:i:s'), new \DateTimeZone('America/Lima')))
            ->setTipoMoneda('PEN')
            ->setCompany($this->company($store, $branch))
            ->setClient($this->client($order, $type))
            ->setMtoOperGravadas($taxable)
            ->setMtoIGV($igv)
            ->setTotalImpuestos($igv)
            ->setValorVenta($taxable)
            ->setSubTotal($total)
            ->setMtoImpVenta($total)
            ->setDetails(array_map(fn (array $line) => $this->detail($line), $lines))
            ->setLegends([(new Legend())->setCode('1000')->setValue(NumberToWords::soles($total))]);

        return $this->paymentTerms($invoice, $order, $total);
    }

    /**
     * Gross (IGV included) per line after sharing the order's discount proportionally, with the
     * rounding difference absorbed by the last line so the lines add up to the order's total.
     *
     * @return list<array{code: ?string, name: string, qty: float, gross: float, value: float, igv: float}>
     */
    private function lines(Order $order): array
    {
        $items = $order->items;
        $subtotal = (float) $items->sum('subtotal');
        $factor = $subtotal > 0 ? ((float) $order->total) / $subtotal : 1.0;

        $lines = [];
        foreach ($items as $item) {
            $gross = round((float) $item->subtotal * $factor, 2);
            $lines[] = [
                'code' => $item->product_sku ?: ($item->product_id ? (string) $item->product_id : null),
                'name' => mb_substr(trim((string) $item->product_name . ($item->variant_title ? ' ' . $item->variant_title : '')), 0, 250) ?: 'Producto',
                'qty'  => (float) $item->quantity,
                'gross' => $gross,
            ];
        }
        if ($lines) {
            $diff = round((float) $order->total - array_sum(array_column($lines, 'gross')), 2);
            $lines[count($lines) - 1]['gross'] = round($lines[count($lines) - 1]['gross'] + $diff, 2);
        }

        return array_map(function (array $line) {
            $value = round($line['gross'] / (1 + SalesDocuments::IGV_RATE), 2);

            return $line + ['value' => $value, 'igv' => round($line['gross'] - $value, 2)];
        }, $lines);
    }

    private function detail(array $line): SaleDetail
    {
        $qty = max(0.0001, $line['qty']);

        return (new SaleDetail())
            ->setUnidad('NIU')
            ->setCantidad($qty)
            ->setCodProducto($line['code'] ? mb_substr($line['code'], 0, 30) : null)
            ->setDescripcion($line['name'])
            ->setMtoBaseIgv($line['value'])
            ->setPorcentajeIgv(SalesDocuments::IGV_RATE * 100)
            ->setIgv($line['igv'])
            ->setTipAfeIgv('10')
            ->setTotalImpuestos($line['igv'])
            ->setMtoValorVenta($line['value'])
            ->setMtoValorUnitario(round($line['value'] / $qty, 10))
            ->setMtoPrecioUnitario(round($line['gross'] / $qty, 10));
    }

    private function company(Store $store, Branch $branch): Company
    {
        return (new Company())
            ->setRuc((string) $store->ruc)
            ->setRazonSocial(mb_strtoupper(trim((string) ($store->legal_name ?: $store->name))))
            ->setNombreComercial(trim((string) $store->name))
            ->setAddress((new Address())
                ->setUbigueo((string) $branch->ubigeo)
                ->setDepartamento(mb_strtoupper((string) $branch->department))
                ->setProvincia(mb_strtoupper((string) $branch->province))
                ->setDistrito(mb_strtoupper((string) $branch->district))
                ->setDireccion(trim((string) $branch->address))
                ->setCodLocal((string) $branch->sunat_code)
                ->setCodigoPais('PE'));
    }

    private function client(Order $order, string $type): Client
    {
        $number = preg_replace('/\D+/', '', (string) $order->customer_document_number);
        $client = new Client();
        if (strlen($number) === 11) {
            $client->setTipoDoc(self::DOC_RUC)->setNumDoc($number);
        } elseif (strlen($number) === 8) {
            $client->setTipoDoc(self::DOC_DNI)->setNumDoc($number);
        } else {
            // A boleta under S/ 700 may go without an identified buyer (assertIssuable already made sure of that).
            $client->setTipoDoc(self::DOC_NONE)->setNumDoc('-');
        }

        $name = trim((string) $order->customer_name);

        return $client->setRznSocial(mb_strtoupper($name !== '' && $name !== 'Cliente varios' ? $name : 'CLIENTE VARIOS'));
    }

    /** Contado, or crédito with its cuotas when the sale left a balance (fiado). */
    private function paymentTerms(Invoice $invoice, Order $order, float $total): Invoice
    {
        $installments = $order->installments;
        if ($installments->isEmpty()) {
            return $invoice->setFormaPago(new FormaPagoContado());
        }

        $owed = round((float) $installments->sum('amount'), 2);

        return $invoice
            ->setFormaPago(new FormaPagoCredito($owed, 'PEN'))
            ->setCuotas($installments->map(fn ($i) => (new Cuota())->setMoneda('PEN')->setMonto(round((float) $i->amount, 2))
                ->setFechaPago(new \DateTime($i->due_date->toDateString(), new \DateTimeZone('America/Lima'))))->all());
    }
}
