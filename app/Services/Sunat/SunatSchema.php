<?php

namespace App\Services\Sunat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The deploy never migrates: until 2026_11_08_200000 has run, sales keep working exactly as before
 * (one series per business, no sedes, nothing sent to SUNAT).
 */
class SunatSchema
{
    private const CACHE_KEY = 'sunat.schema_ready';

    public function ready(): bool
    {
        static $memo = null;
        if ($memo === true || Cache::get(self::CACHE_KEY) === true) {
            return $memo = true;
        }

        try {
            $ready = Schema::hasTable('branches') && Schema::hasTable('sunat_settings') && Schema::hasTable('electronic_documents')
                && Schema::hasColumn('document_series', 'branch_id') && Schema::hasColumn('orders', 'branch_id');
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
        if ($ready) {
            Cache::put(self::CACHE_KEY, true, now()->addDay());
        }

        return $memo = $ready;
    }

    /** Greenter signs and builds the XML; sending it also needs PHP's SOAP extension. */
    public function libraryReady(): bool
    {
        return class_exists(\Greenter\See::class);
    }

    public function soapReady(): bool
    {
        return extension_loaded('soap');
    }
}
