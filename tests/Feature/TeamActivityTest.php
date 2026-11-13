<?php

namespace Tests\Feature;

use App\Models\MemberAccessLog;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use App\Services\Team\AccessLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Equipo: lista compacta y registro de accesos por persona. */
class TeamActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function team(): array
    {
        $owner = User::factory()->create(['role' => 'store_owner', 'name' => 'Jorge Ñahui']);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'El Yunque', 'slug' => 'el-yunque', 'status' => 'active', 'template_name' => 'soft-market', 'mode' => Store::MODE_POS]);
        $ana = User::factory()->create(['role' => 'cliente', 'name' => 'Ana Torres', 'email' => 'ana@gmail.com', 'google_id' => 'g-ana']);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $ana->id, 'email' => $ana->email, 'role' => 'seller', 'modules' => ['vender']]);
        $ana->update(['current_store_id' => $store->id]);

        return [$owner, $store, $ana];
    }

    public function test_signing_in_is_recorded_in_every_business_the_person_works_in(): void
    {
        [$owner, $store, $ana] = $this->team();

        Auth::login($ana);
        $this->assertSame(['login'], MemberAccessLog::where('user_id', $ana->id)->where('store_id', $store->id)->pluck('type')->all());

        Auth::login($owner);
        $this->assertSame(1, MemberAccessLog::where('user_id', $owner->id)->count(), 'the owner is recorded too');
    }

    public function test_using_the_dashboard_is_one_visit_every_half_hour_and_choosing_the_business_is_recorded(): void
    {
        [$owner, $store, $ana] = $this->team();
        Cache::flush();

        $this->actingAs($ana)->get(route('dashboard.pos.index'));
        $this->actingAs($ana)->get(route('dashboard.pos.index'));
        $this->actingAs($ana)->get(route('dashboard.ventas.index'));
        $this->assertSame(1, MemberAccessLog::where('user_id', $ana->id)->where('type', 'visit')->count(), 'many pages, one record');

        $this->actingAs($ana)->post(route('tribio-pass.enter', $store->id));
        $this->assertSame(1, MemberAccessLog::where('user_id', $ana->id)->where('type', 'enter')->count());
    }

    public function test_the_team_page_is_compact_and_shows_last_access_and_the_activity_per_person(): void
    {
        [$owner, $store, $ana] = $this->team();
        MemberAccessLog::create(['store_id' => $store->id, 'user_id' => $ana->id, 'type' => 'login', 'device' => 'Celular · Chrome']);
        $pending = StoreMember::create(['store_id' => $store->id, 'email' => 'luis@gmail.com', 'role' => 'seller']);
        $quiet = User::factory()->create(['role' => 'cliente', 'name' => 'Rosa Quispe', 'email' => 'rosa@gmail.com', 'google_id' => 'g-rosa']);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $quiet->id, 'email' => $quiet->email, 'role' => 'seller']);
        $old = MemberAccessLog::create(['store_id' => $store->id, 'user_id' => $quiet->id, 'type' => 'login']);
        $old->forceFill(['created_at' => now()->subDays(12)])->save();

        $page = $this->actingAs($owner)->get(route('dashboard.team.index'))->assertOk();
        $page->assertSee('Administrador global')->assertSee('Ana Torres')->assertSee('Hoy ')->assertSee('Esperando su Tribio Pass')
            ->assertSee('Hace 12 días')->assertSee('2 activas hoy')->assertSee('1 sin entrar en 7 días')->assertSee('Celular · Chrome')->assertSee('Inició sesión');
        $this->assertStringNotContainsString('Rol</label>', $page->getContent(), 'edit forms stay folded until "Editar"');

        // Filter the activity by person.
        $only = $this->actingAs($owner)->get(route('dashboard.team.index', ['persona' => $quiet->id]))->assertOk();
        $this->assertStringNotContainsString('Celular · Chrome', substr($only->getContent(), (int) strpos($only->getContent(), 'id="actividad"')));
    }

    public function test_when_and_health_labels(): void
    {
        $lima = fn (string $mod) => Carbon::now(AccessLog::TZ)->startOfDay()->modify($mod)->setTimezone('UTC');
        $this->assertSame('Nunca', AccessLog::when(null));
        $this->assertSame('Ayer 10:30', AccessLog::when($lima('-1 day +10 hours +30 minutes')));
        $this->assertSame('Hace 4 días', AccessLog::when($lima('-4 days +9 hours')));
        $this->assertSame(['today', 'recent', 'stale', 'never'], [
            AccessLog::health(now()), AccessLog::health(now()->subDays(3)), AccessLog::health(now()->subDays(20)), AccessLog::health(null),
        ]);
    }
}
