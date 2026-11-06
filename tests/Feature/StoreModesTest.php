<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\RegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tipo de cuenta: sistema POS, tienda virtual o ambos. */
class StoreModesTest extends TestCase
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

    private function business(string $mode, string $slug): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Negocio ' . $slug, 'slug' => $slug, 'status' => 'active', 'template_name' => 'minimal-light', 'mode' => $mode]);

        return [$user, $store];
    }

    public function test_existing_stores_stay_with_both_by_default(): void
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Vieja', 'slug' => 'vieja', 'status' => 'active', 'template_name' => 'minimal-light']);

        $this->assertSame('both', $store->fresh()->mode());
        $this->assertTrue($store->fresh()->hasStorefront() && $store->fresh()->hasPos());
    }

    public function test_a_pos_only_business_has_no_public_page(): void
    {
        [, $pos] = $this->business('pos', 'solo-pos');
        [, $both] = $this->business('both', 'con-tienda');

        $this->get(route('store.show', $pos->slug))->assertNotFound();
        $this->get(route('store.catalog', $pos->slug))->assertNotFound();
        $this->get(route('store.sitemap', $pos->slug))->assertNotFound();
        $this->get(route('store.show', $both->slug))->assertOk();
    }

    public function test_a_pos_only_business_is_left_out_of_the_directory_and_the_home(): void
    {
        [, $pos] = $this->business('pos', 'solo-pos');
        [, $both] = $this->business('both', 'con-tienda');
        $pos->update(['is_featured' => true]);
        $both->update(['is_featured' => true]);

        $this->assertSame(['con-tienda'], Store::online()->pluck('slug')->all());
        $this->get(route('home'))->assertOk()->assertSee('Negocio con-tienda')->assertDontSee('Negocio solo-pos');
        $this->get(route('directory'))->assertOk()->assertDontSee('Negocio solo-pos');
        $this->get('/sitemap.xml')->assertOk()->assertSee('con-tienda')->assertDontSee('solo-pos');
    }

    public function test_the_pos_menu_is_short_and_has_no_online_store_tools(): void
    {
        [$user] = $this->business('pos', 'solo-pos');

        $page = $this->actingAs($user)->get(route('dashboard.index'))->assertOk();
        foreach (['Inicio', 'Vender', 'Ventas', 'Productos', 'Inventario', 'Compras', 'Ganancias y pérdidas', 'Datos y comprobantes'] as $item) {
            $page->assertSee($item);
        }
        foreach (['Plantillas', 'Pasarela de pago', 'Marketing', 'Zonas de envío', 'Reseñas', 'Galería', 'Ver mi tienda'] as $item) {
            $page->assertDontSee($item);
        }
    }

    public function test_the_full_menu_keeps_the_online_store_tools_for_both_and_online(): void
    {
        foreach (['both', 'online'] as $mode) {
            [$user] = $this->business($mode, "negocio-{$mode}");
            $this->actingAs($user)->get(route('dashboard.index'))->assertOk()->assertSee('Plantillas')->assertSee('Pasarela de pago')->assertSee('Ver mi tienda');
        }
        [$online] = $this->business('online', 'solo-online');
        $this->actingAs($online)->get(route('dashboard.index'))->assertOk()->assertDontSee('Por cobrar')->assertDontSee('>Caja<', false);
    }

    public function test_screens_of_the_other_account_type_are_not_reachable_by_url(): void
    {
        [$pos] = $this->business('pos', 'solo-pos');
        [$online] = $this->business('online', 'solo-online');

        foreach (['dashboard.plantillas.index', 'dashboard.gateway.edit', 'dashboard.shipping.index', 'dashboard.marketing.index', 'dashboard.galeria.index'] as $route) {
            $this->actingAs($pos)->get(route($route))->assertRedirect(route('dashboard.index'));
        }
        foreach (['dashboard.pos.index', 'dashboard.ventas.index', 'dashboard.receivables.index', 'dashboard.quotes.index'] as $route) {
            $this->actingAs($online)->get(route($route))->assertRedirect(route('dashboard.index'));
        }
        $this->actingAs($pos)->get(route('dashboard.productos.index'))->assertOk();
        $this->actingAs($pos)->get(route('dashboard.ganancias.index'))->assertOk();
        $this->actingAs($online)->get(route('dashboard.plantillas.index'))->assertOk();
    }

    public function test_the_pos_home_guides_a_new_account_and_shows_the_day(): void
    {
        [$user, $store] = $this->business('pos', 'solo-pos');

        $this->actingAs($user)->get(route('dashboard.index'))->assertOk()
            ->assertSee('Hoy en Negocio solo-pos')->assertSee('Empieza en')->assertSee('Agrega tus productos')->assertSee('Haz tu primera venta')
            ->assertSee('Nueva venta')->assertSee('Ventas de hoy')->assertSee('Ganancia de hoy')->assertSee('Stock bajo')->assertSee('Agotados');

        Product::create(['store_id' => $store->id, 'name' => 'Cemento', 'slug' => 'cemento', 'price' => 30, 'cost_price' => 20, 'stock' => 3, 'track_stock' => true, 'low_stock_alert' => 5, 'is_active' => true]);
        $register = Register::create(['store_id' => $store->id, 'name' => 'Caja 1']);
        $session = app(RegisterService::class)->open($register, 100, $user->id);
        app(PosSaleService::class)->sell($session, ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'items' => [['product_id' => Product::first()->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 30]]], $user->id);

        $this->actingAs($user)->get(route('dashboard.index'))->assertOk()
            ->assertSee('S/ 30.00')->assertSee('1 venta')->assertSee('Por reponer')->assertSee('Cemento')->assertSee('1 caja abierta')->assertSee('Últimas ventas')
            ->assertSee(route('dashboard.productos.index', ['stock' => 'low']), false);
    }

    public function test_only_the_super_admin_changes_the_account_type(): void
    {
        [$owner, $store] = $this->business('both', 'negocio');
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($owner)->patch(route('admin.tiendas.mode', $store), ['mode' => 'pos'])->assertForbidden();
        $this->assertSame('both', $store->fresh()->mode());

        $this->actingAs($admin)->get(route('admin.tiendas.show', $store))->assertOk()->assertSee('Tipo de cuenta')->assertSee('Solo sistema POS');
        $this->actingAs($admin)->patch(route('admin.tiendas.mode', $store), ['mode' => 'pos'])->assertSessionHas('success');
        $this->assertSame('pos', $store->fresh()->mode());
        $this->actingAs($admin)->patch(route('admin.tiendas.mode', $store), ['mode' => 'otra-cosa'])->assertSessionHasErrors('mode');
        $this->assertSame('pos', $store->fresh()->mode());
    }

    public function test_the_command_creates_a_pos_only_business(): void
    {
        $this->artisan('tribio:create-owner', ['email' => 'ferre@example.test', 'store_name' => 'FERRE UNO', '--mode' => 'pos', '--password' => 'Temporal2026x'])->assertSuccessful();
        $this->assertSame('pos', Store::where('slug', 'ferre-uno')->firstOrFail()->mode());

        $this->artisan('tribio:create-owner', ['email' => 'otra@example.test', 'store_name' => 'OTRA', '--mode' => 'raro'])->assertFailed();
        $this->assertSame(1, Store::count());
    }

    public function test_the_sales_page_searches_by_comprobante_customer_and_filters_by_type(): void
    {
        [$user, $store] = $this->business('pos', 'solo-pos');
        $store->update(['ruc' => '20100070970']);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Cemento', 'slug' => 'cemento', 'price' => 30, 'stock' => 100, 'track_stock' => true, 'is_active' => true]);
        $session = app(RegisterService::class)->open(Register::create(['store_id' => $store->id, 'name' => 'Caja 1']), 100, $user->id);
        $sell = fn (array $extra) => app(PosSaleService::class)->sell($session, $extra + ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 30]]], $user->id);
        $sell(['document_type' => 'boleta', 'customer_name' => 'Ana Ruiz', 'customer_document' => '45678912']);
        $sell(['document_type' => 'nota_venta']);
        $sell(['document_type' => 'factura', 'customer_name' => 'Constructora Andina SAC', 'customer_document' => '20131312955']);

        $page = $this->actingAs($user)->get(route('dashboard.ventas.index'))->assertOk();
        $page->assertSee('B001-00000001')->assertSee('NV01-00000001')->assertSee('F001-00000001')->assertSee('<small>Ventas</small><strong>3</strong>', false);

        $this->actingAs($user)->get(route('dashboard.ventas.index', ['q' => 'b001-1']))->assertOk()->assertSee('B001-00000001')->assertDontSee('F001-00000001')->assertSee('<small>Ventas</small><strong>1</strong>', false);
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['q' => 'Andina']))->assertOk()->assertSee('F001-00000001')->assertDontSee('NV01-00000001');
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['q' => '45678912']))->assertOk()->assertSee('B001-00000001')->assertDontSee('F001-00000001');
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['tipo' => 'factura']))->assertOk()->assertSee('F001-00000001')->assertDontSee('B001-00000001');
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['tipo' => 'nota_venta']))->assertOk()->assertSee('NV01-00000001')->assertDontSee('F001-00000001');
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['medio' => 'yape']))->assertOk()->assertSee('No hay ventas');
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['periodo' => 'todas', 'medio' => 'cash']))->assertOk()->assertSee('<small>Ventas</small><strong>3</strong>', false);
    }

    public function test_sales_of_other_stores_never_show_up(): void
    {
        [$user, $store] = $this->business('pos', 'uno');
        [, $other] = $this->business('pos', 'dos');
        $order = new Order();
        $order->forceFill(['store_id' => $other->id, 'order_number' => 'TRB-AJENA', 'customer_name' => 'Cliente ajeno', 'subtotal' => 10, 'total' => 10, 'currency' => 'PEN', 'status' => 'delivered', 'payment_status' => 'paid', 'source' => 'pos'])->save();

        $this->actingAs($user)->get(route('dashboard.ventas.index', ['periodo' => 'todas']))->assertOk()->assertDontSee('TRB-AJENA')->assertSee('<small>Ventas</small><strong>0</strong>', false);
    }
}
