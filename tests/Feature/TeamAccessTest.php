<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use App\Services\Team\TeamAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** Equipo: trabajadores con su propio Tribio Pass, acceso por módulos y entrada directa a la empresa. */
class TeamAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function business(): array
    {
        $owner = User::factory()->create(['role' => 'store_owner', 'email' => 'dueno@ferreteria.pe']);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'Ferretería Norte', 'slug' => 'norte', 'status' => 'active', 'template_name' => 'soft-market', 'mode' => Store::MODE_POS]);

        return [$owner, $store];
    }

    private function worker(string $email = 'ana@gmail.com', array $extra = []): User
    {
        return User::factory()->create($extra + ['role' => 'cliente', 'email' => $email, 'google_id' => 'g-' . $email]);
    }

    public function test_the_owner_adds_a_worker_by_email_and_the_pass_account_is_linked_when_it_exists_or_when_it_is_created(): void
    {
        [$owner, $store] = $this->business();
        $ana = $this->worker();

        $this->actingAs($owner)->post(route('dashboard.team.store'), ['email' => 'ANA@gmail.com', 'role' => 'seller', 'modules' => ['vender', 'clientes']])->assertRedirect(route('dashboard.team.index'));
        $member = StoreMember::sole();
        $this->assertSame([$ana->id, 'ana@gmail.com', ['vender', 'clientes']], [$member->user_id, $member->email, $member->modules], 'an existing Tribio Pass is linked at once');

        // Somebody who has no Tribio Pass yet: waits, and is linked the moment they sign in with Google.
        $this->actingAs($owner)->post(route('dashboard.team.store'), ['email' => 'luis@gmail.com', 'role' => 'seller', 'modules' => ['vender']]);
        $pending = StoreMember::where('email', 'luis@gmail.com')->sole();
        $this->assertNull($pending->user_id);

        $luis = $this->worker('luis@gmail.com');
        Auth::login($luis);
        $this->assertSame($luis->id, $pending->fresh()->user_id);

        // The owner's own e-mail and repeated e-mails are refused.
        $this->actingAs($owner)->post(route('dashboard.team.store'), ['email' => 'dueno@ferreteria.pe', 'role' => 'seller'])->assertSessionHasErrors('email');
        $this->actingAs($owner)->post(route('dashboard.team.store'), ['email' => 'ana@gmail.com', 'role' => 'seller'])->assertSessionHasErrors('email');
    }

    public function test_a_google_sign_up_with_a_very_long_photo_url_still_creates_the_account(): void
    {
        $url = 'https://lh3.googleusercontent.com/a-/' . str_repeat('A', 400);
        $user = app(\App\Services\CustomerIdentityService::class)->findOrCreateFromGoogle('g-long', 'isai@gmail.com', 'Isai Sedano', $url);

        $this->assertSame(['isai@gmail.com', 'g-long'], [$user->email, $user->google_id]);
    }

    public function test_an_unverified_account_does_not_claim_an_invitation(): void
    {
        [$owner, $store] = $this->business();
        StoreMember::create(['store_id' => $store->id, 'email' => 'eva@gmail.com', 'role' => 'seller']);

        $eva = User::factory()->create(['role' => 'cliente', 'email' => 'eva@gmail.com', 'google_id' => null, 'email_verified_at' => null]);
        $this->assertSame(0, app(TeamAccess::class)->claimInvites($eva));
        $this->assertNull(StoreMember::sole()->user_id);
    }

    public function test_the_hub_offers_entering_the_business_first_and_the_worker_lands_on_what_they_may_use(): void
    {
        [$owner, $store] = $this->business();
        $ana = $this->worker();
        StoreMember::create(['store_id' => $store->id, 'user_id' => $ana->id, 'email' => $ana->email, 'role' => 'seller', 'modules' => ['vender', 'ventas']]);

        $this->actingAs($ana)->get(route('tribio-pass'))->assertOk()->assertSee('Ingresar a Ferretería Norte')->assertSee('Vendedor');

        $this->actingAs($ana)->post(route('tribio-pass.enter', $store->id))->assertRedirect(route('dashboard.index'));
        $this->assertSame($store->id, $ana->fresh()->currentStore()->id);
        $this->actingAs($ana)->get(route('dashboard.index'))->assertRedirect(route('dashboard.pos.index'));
        $this->actingAs($ana)->get(route('dashboard.pos.index'))->assertOk();

        // Somebody outside the team cannot enter.
        $other = $this->worker('otro@gmail.com');
        $this->actingAs($other)->post(route('tribio-pass.enter', $store->id))->assertForbidden();
    }

    public function test_a_seller_only_opens_the_modules_ticked_for_them(): void
    {
        [$owner, $store] = $this->business();
        $ana = $this->worker();
        $member = StoreMember::create(['store_id' => $store->id, 'user_id' => $ana->id, 'email' => $ana->email, 'role' => 'seller', 'modules' => ['vender']]);
        $ana->update(['current_store_id' => $store->id]);

        $this->actingAs($ana)->get(route('dashboard.pos.index'))->assertOk();
        foreach (['dashboard.productos.index', 'dashboard.ganancias.index', 'dashboard.team.index', 'dashboard.modules.edit', 'dashboard.clientes.index', 'dashboard.pos.staff.index', 'dashboard.store.edit'] as $route) {
            $this->actingAs($ana)->get(route($route))->assertRedirect(route('dashboard.pos.index'));
        }

        $menu = $this->actingAs($ana)->get(route('dashboard.pos.index'))->getContent();
        $this->assertStringNotContainsString('href="' . route('dashboard.ganancias.index') . '"', $menu);
        $this->assertStringNotContainsString('href="' . route('dashboard.team.index') . '"', $menu);
        $this->assertStringNotContainsString('href="' . route('dashboard.modules.edit') . '"', $menu);

        // Opening a module is immediate.
        $member->update(['modules' => ['vender', 'productos']]);
        $this->actingAs($ana)->get(route('dashboard.productos.index'))->assertOk();
        $this->assertStringContainsString('href="' . route('dashboard.productos.index') . '"', $this->actingAs($ana)->get(route('dashboard.pos.index'))->getContent());
    }

    public function test_an_admin_opens_everything_and_a_switched_off_member_nothing(): void
    {
        [$owner, $store] = $this->business();
        $boss = $this->worker('jefe@gmail.com');
        $member = StoreMember::create(['store_id' => $store->id, 'user_id' => $boss->id, 'email' => $boss->email, 'role' => 'admin']);

        $this->actingAs($boss)->post(route('tribio-pass.enter', $store->id));
        $this->actingAs($boss)->get(route('dashboard.ganancias.index'))->assertOk();
        $this->actingAs($boss)->get(route('dashboard.team.index'))->assertOk()->assertSee('Administrador global');

        $member->update(['is_active' => false]);
        $this->actingAs($boss)->get(route('tribio-pass'))->assertOk()->assertDontSee('Ingresar a Ferretería Norte');
        $this->actingAs($boss)->get(route('dashboard.ganancias.index'))->assertForbidden();
    }

    public function test_a_seller_sells_with_their_own_name_and_cannot_touch_another_business(): void
    {
        [$owner, $store] = $this->business();
        $ana = $this->worker();
        StoreMember::create(['store_id' => $store->id, 'user_id' => $ana->id, 'email' => $ana->email, 'role' => 'seller', 'modules' => ['vender']]);
        $ana->update(['current_store_id' => $store->id]);

        $this->assertTrue(app(TeamAccess::class)->allows($ana, $store, 'dashboard.pos.sell'));
        $this->assertFalse(app(TeamAccess::class)->allows($ana, $store, 'dashboard.pos.documents.edit'), 'series, SUNAT and PINs stay with the managers');

        $otherOwner = User::factory()->create(['role' => 'store_owner']);
        $otherStore = Store::create(['user_id' => $otherOwner->id, 'name' => 'Otra', 'slug' => 'otra', 'status' => 'active', 'template_name' => 'soft-market', 'mode' => Store::MODE_POS]);
        $this->assertNull(app(TeamAccess::class)->roleIn($ana, $otherStore));
        $this->actingAs($ana)->post(route('dashboard.store.switch', $otherStore->id))->assertForbidden();
    }

    public function test_only_managers_manage_the_team_and_only_the_owner_hands_out_admin(): void
    {
        [$owner, $store] = $this->business();
        $ana = $this->worker();
        StoreMember::create(['store_id' => $store->id, 'user_id' => $ana->id, 'email' => $ana->email, 'role' => 'seller', 'modules' => ['vender']]);
        $ana->update(['current_store_id' => $store->id]);
        $this->actingAs($ana)->post(route('dashboard.team.store'), ['email' => 'x@gmail.com', 'role' => 'seller'])->assertRedirect(route('dashboard.pos.index'));
        $this->assertSame(1, StoreMember::count());

        $boss = $this->worker('jefe@gmail.com');
        StoreMember::create(['store_id' => $store->id, 'user_id' => $boss->id, 'email' => $boss->email, 'role' => 'admin']);
        $boss->update(['current_store_id' => $store->id]);
        $this->actingAs($boss)->post(route('dashboard.team.store'), ['email' => 'nuevo@gmail.com', 'role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame('seller', StoreMember::where('email', 'nuevo@gmail.com')->sole()->role, 'an admin cannot create another admin');
    }
}
