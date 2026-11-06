<?php

namespace App\Console\Commands;

use App\Services\Sunat\ElectronicDocuments;
use App\Services\Sunat\SunatSchema;
use Illuminate\Console\Command;

class SunatRetry extends Command
{
    protected $signature = 'sunat:reenviar {--limit=50}';

    protected $description = 'Reintenta enviar a SUNAT los comprobantes que no llegaron (sin conexión, SUNAT caído…)';

    public function handle(SunatSchema $schema, ElectronicDocuments $documents): int
    {
        if (!$schema->ready()) {
            $this->info('Facturación electrónica sin migrar: nada que reenviar.');

            return self::SUCCESS;
        }
        $this->info('Comprobantes reintentados: ' . $documents->retryDue((int) $this->option('limit')));

        return self::SUCCESS;
    }
}
