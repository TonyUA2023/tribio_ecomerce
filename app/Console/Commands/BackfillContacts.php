<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Crm\ContactResolver;
use App\Services\Crm\CrmSchema;
use Illuminate\Console\Command;

/**
 * Creates the contacts of orders placed before Dashboard → Clientes existed. Safe to run
 * again: it only visits orders without a contact. New orders are linked by OrderObserver.
 */
class BackfillContacts extends Command
{
    protected $signature = 'crm:backfill-contacts {--store= : Solo los pedidos de esta tienda (id)}';

    protected $description = 'Crea los contactos de los pedidos antiguos y los enlaza';

    public function handle(CrmSchema $schema, ContactResolver $resolver): int
    {
        if (!$schema->ready()) {
            $this->error('Falta correr la migración 2026_11_02_100000_create_contacts_table.');
            return self::FAILURE;
        }

        $query = Order::query()->whereNull('contact_id')->orderBy('id');
        if ($this->option('store')) {
            $query->where('store_id', (int) $this->option('store'));
        }

        $linked = 0;
        $skipped = 0;
        $query->chunkById(200, function ($orders) use ($resolver, &$linked, &$skipped) {
            foreach ($orders as $order) {
                $resolver->linkOrder($order) ? $linked++ : $skipped++;
            }
        });

        $this->info("Pedidos enlazados: {$linked}. Sin teléfono, correo ni documento: {$skipped}.");

        return self::SUCCESS;
    }
}
