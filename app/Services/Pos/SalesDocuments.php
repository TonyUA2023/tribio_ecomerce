<?php

namespace App\Services\Pos;

use App\Models\DocumentSeries;
use App\Models\Order;
use App\Models\Register;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Comprobantes de venta del Perú: nota de venta (interna, sin valor tributario), boleta de venta
 * y factura. Cada negocio tiene una serie por tipo (NV01 / B001 / F001) con su correlativo; el
 * número se toma dentro de la misma transacción de la venta, bajo bloqueo, para que dos cajas
 * nunca emitan el mismo.
 *
 * Esta clase numera y valida los datos que exige el comprobante (RUC del emisor, RUC y razón
 * social para la factura, DNI en boletas de más de S/ 700). NO envía nada a SUNAT: la emisión
 * electrónica necesita un OSE/PSE y es un paso aparte.
 */
class SalesDocuments
{
    public const NOTA = 'nota_venta';
    public const BOLETA = 'boleta';
    public const FACTURA = 'factura';

    public const TYPES = [
        self::NOTA    => ['label' => 'Nota de venta',   'short' => 'Nota',    'series' => 'NV01', 'fiscal' => false],
        self::BOLETA  => ['label' => 'Boleta de venta', 'short' => 'Boleta',  'series' => 'B001', 'fiscal' => true],
        self::FACTURA => ['label' => 'Factura',         'short' => 'Factura', 'series' => 'F001', 'fiscal' => true],
    ];

    /** Precios con IGV incluido: el 18 % se desglosa del total en boletas y facturas. */
    public const IGV_RATE = 0.18;

    /** SUNAT: una boleta de más de este monto debe identificar al comprador. */
    public const BOLETA_ID_LIMIT = 700.00;

    private const CACHE_KEY = 'pos.documents_ready';

    /** The deploy never migrates: until 2026_11_06_100000 has run every sale stays a plain nota de venta. */
    public function ready(): bool
    {
        if (Cache::get(self::CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = Schema::hasTable('document_series') && Schema::hasColumn('orders', 'document_number') && Schema::hasColumn('stores', 'ruc');
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
        if ($ready) {
            Cache::put(self::CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    public function normalize(?string $type): string
    {
        return isset(self::TYPES[$type ?? '']) ? $type : self::NOTA;
    }

    /** @return array<string, string> type => label */
    public static function labels(): array
    {
        return array_map(fn ($meta) => $meta['label'], self::TYPES);
    }

    /** Series of one sede or one caja exist only once the sedes migration has run. */
    private function scoped(): bool
    {
        return app(\App\Services\Sunat\SunatSchema::class)->ready();
    }

    /** The store's default series (the ones of the whole business), created with their default prefix the first time. @return Collection<string, DocumentSeries> keyed by type */
    public function series(Store $store): Collection
    {
        foreach (self::TYPES as $type => $meta) {
            $this->defaultSeries($store->id, $type);
        }

        return DocumentSeries::where('store_id', $store->id)
            ->when($this->scoped(), fn ($q) => $q->whereNull('branch_id')->whereNull('register_id'))->get()->keyBy('type');
    }

    private function defaultSeries(int $storeId, string $type): DocumentSeries
    {
        $keys = ['store_id' => $storeId, 'type' => $type] + ($this->scoped() ? ['branch_id' => null, 'register_id' => null] : []);

        return DocumentSeries::firstOrCreate($keys, ['series' => self::TYPES[$type]['series'], 'last_number' => 0]);
    }

    /**
     * The series a sale is numbered with: the one of its caja, else the one of its sede, else the
     * business's. That lets each caja or sede keep its own B00x / F00x, as SUNAT allows.
     */
    public function seriesFor(int $storeId, string $type, ?Register $register = null): DocumentSeries
    {
        if ($register && $this->scoped()) {
            $row = DocumentSeries::where('store_id', $storeId)->where('type', $type)->where('register_id', $register->id)->first()
                ?? ($register->branch_id ? DocumentSeries::where('store_id', $storeId)->where('type', $type)->where('branch_id', $register->branch_id)->whereNull('register_id')->first() : null);
            if ($row) {
                return $row;
            }
        }

        return $this->defaultSeries($storeId, $type);
    }

    /**
     * Throws a validation error (key `document_type`) when the sale cannot be issued as that document.
     *
     * @param  ?string  $document  DNI or RUC of the buyer, digits only or not
     */
    public function assertIssuable(Store $store, string $type, ?string $document, ?string $name, float $total): void
    {
        if ($type === self::NOTA) {
            return;
        }
        if (!self::validRuc((string) $store->ruc)) {
            $this->refuse('Para emitir boletas y facturas, registra el RUC de tu negocio en Caja → Comprobantes.');
        }

        $document = preg_replace('/\D+/', '', (string) $document);
        if ($type === self::FACTURA) {
            if (!self::validRuc($document)) {
                $this->refuse('La factura necesita el RUC del cliente (11 dígitos válidos).');
            }
            if (trim((string) $name) === '' || $name === PosSaleService::WALK_IN) {
                $this->refuse('La factura necesita la razón social del cliente.');
            }
        } elseif ($total > self::BOLETA_ID_LIMIT + 0.004 && strlen($document) < 8) {
            $this->refuse('Una boleta de más de S/ ' . number_format(self::BOLETA_ID_LIMIT, 0) . ' necesita el DNI del cliente.');
        }
    }

    /** Gives the order the next number of its type's series. Call it inside the sale's transaction. */
    public function issue(Order $order, string $type, ?Register $register = null): void
    {
        $series = $this->seriesFor($order->store_id, $type, $register);
        $locked = DocumentSeries::whereKey($series->id)->lockForUpdate()->firstOrFail();
        // The counter can fall behind what was really issued (a series switched back to an old code, a restored backup):
        // numbering goes on after the highest number of that series, never into one already taken.
        $issued = (int) Order::where('store_id', $order->store_id)->where('document_type', $type)->where('document_series', $locked->series)->max('document_number');
        $next = max((int) $locked->last_number, $issued) + 1;
        $locked->update(['last_number' => $next]);

        $order->forceFill(['document_type' => $type, 'document_series' => $locked->series, 'document_number' => $next])->save();
    }

    public static function code(?string $series, ?int $number): ?string
    {
        return $series && $number ? sprintf('%s-%08d', $series, $number) : null;
    }

    /** Operación gravada and IGV of a total that already includes it. @return array{taxable: float, igv: float} */
    public static function breakdown(float $total): array
    {
        $taxable = round($total / (1 + self::IGV_RATE), 2);

        return ['taxable' => $taxable, 'igv' => round($total - $taxable, 2)];
    }

    /** RUC: 11 digits, SUNAT prefix and modulo-11 check digit. */
    public static function validRuc(string $ruc): bool
    {
        if (!preg_match('/^(10|15|16|17|20)\d{9}$/', $ruc)) {
            return false;
        }
        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        foreach ($weights as $i => $weight) {
            $sum += (int) $ruc[$i] * $weight;
        }

        return (11 - $sum % 11) % 10 === (int) $ruc[10];
    }

    /** The 10 first digits plus their check digit: a valid RUC (used to build demo data). */
    public static function rucFrom(string $firstTen): string
    {
        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        foreach ($weights as $i => $weight) {
            $sum += (int) $firstTen[$i] * $weight;
        }

        return $firstTen . (11 - $sum % 11) % 10;
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['document_type' => $message]);
    }
}
