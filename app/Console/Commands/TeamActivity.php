<?php

namespace App\Console\Commands;

use App\Models\MemberAccessLog;
use App\Models\Store;
use App\Models\StoreMember;
use App\Services\Team\AccessLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Did the people of a business use the system today? Read from the server, no web login needed. */
class TeamActivity extends Command
{
    protected $signature = 'tribio:team-activity
        {store=el-yunque : Slug of the business}
        {--days=1 : How many days back to look (1 = today, Lima time)}';

    protected $description = 'Show who of a business signed in or used the system today (or in the last N days)';

    public function handle(): int
    {
        $store = Store::where('slug', $this->argument('store'))->first();
        if (!$store) {
            $this->error("No store with slug '{$this->argument('store')}'.");

            return self::FAILURE;
        }
        if (!AccessLog::ready()) {
            $this->error('Falta la migración del registro de accesos: corre php artisan migrate --force. Solo se registran accesos desde que está activa.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $from = Carbon::now(AccessLog::TZ)->startOfDay()->subDays($days - 1)->setTimezone('UTC');
        $logs = MemberAccessLog::where('store_id', $store->id)->where('created_at', '>=', $from)->orderBy('created_at')->get()->groupBy('user_id');

        $people = collect([['name' => $store->user?->name ?? 'Dueño', 'email' => $store->user?->email, 'role' => 'Administrador global', 'user_id' => $store->user_id, 'active' => true]]);
        foreach (StoreMember::where('store_id', $store->id)->with('user:id,name,email')->get() as $m) {
            $people->push(['name' => $m->user?->name ?? $m->name ?? $m->email, 'email' => $m->email, 'role' => $m->isAdmin() ? 'Administrador' : 'Vendedor', 'user_id' => $m->user_id, 'active' => $m->is_active]);
        }

        $this->info($store->name . ' · ' . ($days === 1 ? 'hoy' : "últimos {$days} días") . ' (hora de Lima)');
        $rows = $people->map(function (array $p) use ($logs) {
            $mine = $p['user_id'] ? ($logs[$p['user_id']] ?? collect()) : collect();
            $first = $mine->first()?->created_at;
            $last = $mine->last()?->created_at;
            $fmt = fn (?Carbon $d) => $d ? $d->copy()->setTimezone(AccessLog::TZ)->format('d/m H:i') : '—';

            return [
                $p['name'], $p['email'], $p['role'],
                !$p['user_id'] ? 'Sin Tribio Pass' : ($mine->isNotEmpty() ? 'SÍ entró' : ($p['active'] ? 'NO entró' : 'Sin acceso')),
                $fmt($first), $fmt($last), $mine->count(),
            ];
        })->all();

        $this->table(['Persona', 'Correo', 'Rol', 'Entró', 'Primer acceso', 'Último acceso', 'Registros'], $rows);

        return self::SUCCESS;
    }
}
