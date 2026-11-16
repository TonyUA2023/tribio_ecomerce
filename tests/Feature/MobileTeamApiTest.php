<?php

namespace Tests\Feature;

use App\Models\MemberAccessLog;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The mobile API for people who work in a business: login, menu profile, module guard and Equipo. */
class MobileTeamApiTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function business(): array
    {
        $owner = User::factory()->create(['role' => 'store_owner', 'email' => 'dueno@yunque.pe']);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'El Yunque', 'slug' => 'el-yunque', 'status' => 'active', 'template_name' => 'soft-market', 'mode' => Store::MODE_POS]);

        return [$owner, $store];
    }

    private function member(Store $store, string $email, string $role = 'seller', array $modules = ['vender']): User
    {
        $user = User::factory()->create(['role' => 'cliente', 'email' => $email, 'password' => 'secret1234', 'name' => ucfirst(strtok($email, '@'))]);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $user->id, 'email' => $email, 'role' => $role, 'modules' => $modules]);
        $user->update(['current_store_id' => $store->id]);

        return $user;
    }

    public function test_a_team_member_can_sign_in_from_the_app_and_it_is_logged(): void
    {
        [, $store] = $this->business();
        $ana = $this->member($store, 'ana@gmail.com');

        $this->postJson('/api/login', ['email' => 'ana@gmail.com', 'password' => 'secret1234'])->assertOk()->assertJsonPath('store.id', $store->id);
        $this->assertSame(1, MemberAccessLog::where('user_id', $ana->id)->where('type', 'login')->count());

        // A plain buyer with no business and no team is still refused.
        User::factory()->create(['role' => 'cliente', 'email' => 'comprador@gmail.com', 'password' => 'secret1234']);
        $this->postJson('/api/login', ['email' => 'comprador@gmail.com', 'password' => 'secret1234'])->assertForbidden();
    }

    public function test_the_profile_tells_the_phone_which_menu_to_draw(): void
    {
        [$owner, $store] = $this->business();
        $ana = $this->member($store, 'ana@gmail.com', 'seller', ['vender', 'clientes']);

        Sanctum::actingAs($owner, ['*']);
        $this->getJson('/api/dashboard/espacio')->assertOk()->assertJson(['role' => 'owner', 'manages' => true, 'mode' => 'pos', 'has_storefront' => false, 'has_pos' => true])
            ->assertJsonPath('modules.0', 'vender');

        Sanctum::actingAs($ana, ['*']);
        $this->getJson('/api/dashboard/espacio')->assertOk()->assertJson(['role' => 'seller', 'manages' => false, 'modules' => ['vender', 'clientes']]);
    }

    public function test_a_seller_reaches_only_the_areas_their_modules_open(): void
    {
        [, $store] = $this->business();
        $ana = $this->member($store, 'ana@gmail.com', 'seller', ['vender']);
        Sanctum::actingAs($ana, ['*']);

        foreach (['/api/dashboard/stats', '/api/dashboard/tienda', '/api/dashboard/pasarela', '/api/dashboard/marketing', '/api/dashboard/equipo', '/api/dashboard/categorias'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->getJson('/api/dashboard/cuenta')->assertOk();
        $this->getJson('/api/dashboard/tiendas')->assertOk();

        StoreMember::where('user_id', $ana->id)->update(['modules' => ['vender', 'productos']]);
        $this->getJson('/api/dashboard/categorias')->assertOk();
    }

    public function test_the_stores_list_carries_the_role_and_switching_needs_access(): void
    {
        [, $store] = $this->business();
        $ana = $this->member($store, 'ana@gmail.com');
        $otherOwner = User::factory()->create(['role' => 'store_owner']);
        $other = Store::create(['user_id' => $otherOwner->id, 'name' => 'Otra', 'slug' => 'otra', 'status' => 'active', 'template_name' => 'soft-market', 'mode' => Store::MODE_POS]);
        Sanctum::actingAs($ana, ['*']);

        $this->getJson('/api/dashboard/tiendas')->assertOk()->assertJsonPath('stores.0.role', 'seller')->assertJsonPath('stores.0.id', $store->id);
        $this->postJson("/api/dashboard/tiendas/{$store->id}/cambiar")->assertOk();
        $this->postJson("/api/dashboard/tiendas/{$other->id}/cambiar")->assertNotFound();
    }

    public function test_the_owner_manages_the_team_from_the_phone_and_sees_who_entered(): void
    {
        [$owner, $store] = $this->business();
        $ana = $this->member($store, 'ana@gmail.com', 'seller', ['vender']);
        MemberAccessLog::create(['store_id' => $store->id, 'user_id' => $ana->id, 'type' => 'login', 'device' => 'Celular · Chrome']);
        Sanctum::actingAs($owner, ['*']);

        $this->getJson('/api/dashboard/equipo')->assertOk()
            ->assertJsonPath('owner.role', 'owner')->assertJsonPath('members.0.email', 'ana@gmail.com')->assertJsonPath('members.0.status', 'today')
            ->assertJsonPath('summary.people', 2)->assertJsonPath('can_create_admin', true);

        $this->postJson('/api/dashboard/equipo', ['email' => 'luis@gmail.com', 'role' => 'seller', 'modules' => ['vender', 'ventas']])->assertCreated();
        $luis = StoreMember::where('email', 'luis@gmail.com')->firstOrFail();
        $this->getJson('/api/dashboard/equipo')->assertJsonPath('members.1.status', 'pending');
        $this->postJson('/api/dashboard/equipo', ['email' => 'luis@gmail.com', 'role' => 'seller'])->assertStatus(422);
        $this->postJson('/api/dashboard/equipo', ['email' => 'dueno@yunque.pe', 'role' => 'seller'])->assertStatus(422);

        $this->putJson("/api/dashboard/equipo/{$luis->id}", ['role' => 'seller', 'modules' => ['vender'], 'is_active' => false, 'name' => 'Luis'])->assertOk();
        $this->assertSame([false, ['vender'], 'Luis'], [$luis->fresh()->is_active, $luis->fresh()->modules, $luis->fresh()->name]);

        $this->getJson('/api/dashboard/equipo/actividad')->assertOk()->assertJsonFragment(['type' => 'login', 'device' => 'Celular · Chrome']);
        $this->deleteJson("/api/dashboard/equipo/{$luis->id}")->assertOk();
        $this->assertNull(StoreMember::find($luis->id));
    }

    public function test_sellers_cannot_manage_the_team_and_admins_cannot_create_admins(): void
    {
        [, $store] = $this->business();
        $ana = $this->member($store, 'ana@gmail.com', 'seller', ['vender']);
        $boss = $this->member($store, 'jefe@gmail.com', 'admin', []);

        Sanctum::actingAs($ana, ['*']);
        $this->postJson('/api/dashboard/equipo', ['email' => 'x@gmail.com', 'role' => 'seller'])->assertForbidden();

        Sanctum::actingAs($boss, ['*']);
        $this->postJson('/api/dashboard/equipo', ['email' => 'nuevo@gmail.com', 'role' => 'admin'])->assertCreated();
        $this->assertSame('seller', StoreMember::where('email', 'nuevo@gmail.com')->value('role'));
        $adminRow = StoreMember::where('user_id', $boss->id)->first();
        $this->deleteJson("/api/dashboard/equipo/{$adminRow->id}")->assertForbidden();
    }

    public function test_the_app_visit_is_logged_once_every_half_hour(): void
    {
        [, $store] = $this->business();
        $ana = $this->member($store, 'ana@gmail.com');
        Cache::flush();
        Sanctum::actingAs($ana, ['*']);

        $this->getJson('/api/dashboard/espacio');
        $this->getJson('/api/dashboard/tiendas');
        $this->assertSame(1, MemberAccessLog::where('user_id', $ana->id)->where('type', 'visit')->count());
    }
}
