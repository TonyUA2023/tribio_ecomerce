<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Order;
use App\Models\Product;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Configuración → Módulos: tienda virtual, POS y CRM se encienden por separado y el menú se adapta. */
class ModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function owner(string $mode = Store::MODE_BOTH, string $slug = 'ferre'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería Norte', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market', 'mode' => $mode]);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Cemento', 'slug' => 'cemento', 'price' => 30, 'stock' => 100, 'track_stock' => true, 'is_active' => true]);

        return [$user, $store, $product];
    }

    private function save(User $user, array $modules)
    {
        return $this->actingAs($user)->put(route('dashboard.modules.update'), $modules);
    }

    private function nav(User $user): string
    {
        return $this->actingAs($user)->get(route('dashboard.productos.index'))->assertOk()->getContent();
    }

    public function test_the_page_shows_every_module_with_what_it_enables(): void
    {
        [$user] = $this->owner();

        $this->actingAs($user)->get(route('dashboard.modules.edit'))->assertOk()
            ->assertSee('Abrir tienda virtual')->assertSee('Usar el punto de venta')->assertSee('Usar el CRM')->assertSee('Siempre incluido')->assertSee('Módulos del negocio');
    }

    public function test_turning_the_online_store_off_leaves_the_pos_and_hides_the_store_tools(): void
    {
        [$user, $store] = $this->owner();
        $this->assertStringContainsString('Zonas de envío', $this->nav($user));

        $this->save($user, ['pos' => '1', 'crm' => '1'])->assertRedirect(route('dashboard.modules.edit'));
        $store->refresh();
        $this->assertSame([Store::MODE_POS, false, true], [$store->mode(), $store->hasStorefront(), $store->hasPos()]);

        $menu = $this->nav($user);
        $this->assertStringNotContainsString('Zonas de envío', $menu);
        $this->assertStringContainsString('Ventas', $menu);
        $this->actingAs($user)->get(route('dashboard.shipping.index'))->assertRedirect(route('dashboard.index'));
        $this->get(route('store.show', $store->slug))->assertNotFound();
        $this->assertFalse(Store::online()->whereKey($store->id)->exists());
    }

    public function test_opening_the_online_store_enables_its_tools_and_takes_you_to_set_it_up(): void
    {
        [$user, $store] = $this->owner(Store::MODE_POS);
        $this->assertStringNotContainsString('Zonas de envío', $this->nav($user));

        $this->save($user, ['store' => '1', 'pos' => '1', 'crm' => '1'])->assertRedirect(route('dashboard.store.edit'))->assertSessionHas('success');
        $this->assertSame(Store::MODE_BOTH, $store->fresh()->mode());
        $this->assertStringContainsString('Zonas de envío', $this->nav($user));
        $this->actingAs($user)->get(route('dashboard.shipping.index'))->assertOk();
        $this->assertTrue(Store::online()->whereKey($store->id)->exists());
    }

    public function test_the_store_alone_has_no_register_and_the_pos_alone_needs_no_store(): void
    {
        [$user, $store] = $this->owner();

        $this->save($user, ['store' => '1', 'crm' => '1']);
        $this->assertSame(Store::MODE_ONLINE, $store->fresh()->mode());
        $this->actingAs($user)->get(route('dashboard.ventas.index'))->assertRedirect(route('dashboard.index'));
        $this->actingAs($user)->get(route('dashboard.pedidos.index'))->assertOk();

        $this->save($user, ['pos' => '1', 'crm' => '1']);
        $this->assertSame(Store::MODE_POS, $store->fresh()->mode());
        $this->actingAs($user)->get(route('dashboard.ventas.index'))->assertOk();
    }

    public function test_at_least_one_of_store_or_pos_must_stay_on(): void
    {
        [$user, $store] = $this->owner();

        $this->save($user, ['crm' => '1'])->assertSessionHasErrors('pos');
        $this->assertSame(Store::MODE_BOTH, $store->fresh()->mode());
    }

    public function test_the_quick_screen_only_applies_to_a_pos_without_store(): void
    {
        [$user, $store] = $this->owner();

        $this->save($user, ['pos' => '1', 'simple' => '1']);
        $this->assertTrue($store->fresh()->isSimple());
        $this->actingAs($user)->get(route('dashboard.index'))->assertRedirect(route('dashboard.simple.index'));

        $this->save($user, ['store' => '1', 'pos' => '1', 'simple' => '1']);
        $this->assertSame([Store::MODE_BOTH, false], [$store->fresh()->mode(), $store->fresh()->isSimple()], 'with a store the quick screen does not apply');
    }

    public function test_the_crm_goes_off_without_touching_the_pos(): void
    {
        [$user, $store, $cement] = $this->owner(Store::MODE_POS);
        $this->assertTrue($store->hasCrm(), 'every business keeps its CRM until it says otherwise');
        $this->assertStringContainsString('Clientes', $this->nav($user));

        $this->save($user, ['pos' => '1'])->assertSessionHasNoErrors();
        $this->assertFalse($store->fresh()->hasCrm());

        $menu = $this->nav($user);
        $this->assertStringNotContainsString('href="' . route('dashboard.clientes.index') . '"', $menu);
        $this->assertStringNotContainsString('href="' . route('dashboard.whatsapp.index') . '"', $menu);
        $this->actingAs($user)->get(route('dashboard.clientes.index'))->assertRedirect(route('dashboard.index'))->assertSessionHas('info');
        $this->actingAs($user)->get(route('dashboard.whatsapp.index'))->assertRedirect(route('dashboard.index'));

        // The register keeps working, even selling on credit to someone who is named (the customer is remembered quietly).
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk();
        $register = Register::where('store_id', $store->id)->firstOrFail();
        $this->actingAs($user)->post(route('dashboard.pos.registers.open', $register->id), ['opening_float' => 10]);
        $session = RegisterSession::where('register_id', $register->id)->where('status', 'open')->firstOrFail();
        $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), [
            'uuid' => (string) Str::uuid(), 'items' => [['product_id' => $cement->id, 'quantity' => 2]], 'customer_name' => 'Pedro Salazar', 'customer_phone' => '987654321',
            'payments' => [['method' => 'cash', 'amount' => 60]],
        ])->assertSuccessful();
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Contact::count(), 'the buyer is saved even with the CRM screens hidden');
        $this->actingAs($user)->get(route('dashboard.ventas.index'))->assertOk()->assertSee('Pedro Salazar');
        $this->actingAs($user)->get(route('dashboard.receivables.index'))->assertOk();

        // Turned on again, everything is where it was.
        $this->save($user, ['pos' => '1', 'crm' => '1']);
        $this->actingAs($user)->get(route('dashboard.clientes.index'))->assertOk()->assertSee('Pedro Salazar');
    }

    public function test_the_menu_keeps_its_sections_when_the_crm_is_off(): void
    {
        [$user] = $this->owner();
        $this->save($user, ['store' => '1', 'pos' => '1']);

        $menu = $this->nav($user);
        $this->assertStringContainsString('Ganancias y pérdidas', $menu);
        $this->assertStringContainsString('Configuración', $menu);
        $this->assertMatchesRegularExpression('~Clientes y reportes.{0,400}Ganancias y pérdidas~s', $menu, 'the section header moved on to the item that follows');
        $this->assertStringNotContainsString('href="' . route('dashboard.clientes.index') . '"', $menu);
    }
}
