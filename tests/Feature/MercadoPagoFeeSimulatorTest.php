<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Payments\MercadoPagoFees;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Simulador de comisiones de Mercado Pago del formulario de productos. */
class MercadoPagoFeeSimulatorTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        if (config('database.default') !== 'sqlite') {
            return [];
        }

        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function store(array $attributes = []): Store
    {
        $owner = User::factory()->create(['role' => 'store_owner']);

        return Store::create($attributes + [
            'user_id' => $owner->id, 'name' => 'Tienda', 'slug' => 'tienda-' . $owner->id, 'status' => 'active',
            'template_name' => 'soft-market', 'payment_gateway' => 'mercado_pago',
        ]);
    }

    public function test_matches_the_mercado_pago_simulator_example(): void
    {
        $fees = app(MercadoPagoFees::class);

        // Captura del simulador oficial: recibir S/25,00 con Checkout + débito + en el momento → cobrar S/27,30.
        $r = $fees->fromNet(25, 'debit', 'instant');
        $this->assertSame(27.30, $r['charge']);
        $this->assertSame(2.30, $r['fee']);
        $this->assertSame(25.0, $r['net']);
        $this->assertSame('3,49% + S/1,00 + IGV', $r['summary']);

        $back = $fees->fromCharge(27.30, 'debit', 'instant');
        $this->assertSame(25.0, $back['net']);
    }

    public function test_the_charge_for_a_net_never_leaves_the_owner_short(): void
    {
        $fees = app(MercadoPagoFees::class);

        foreach ([0.5, 9.9, 49.9, 100, 129.99, 1234.56] as $target) {
            $r = $fees->fromNet($target, 'debit', 'instant');
            $this->assertGreaterThanOrEqual($target, $r['net'], "target {$target}");
            $this->assertLessThan($target + 0.03, $r['net'], "target {$target} should not overcharge by more than 2 cents");
        }
        $this->assertSame(0.0, $fees->fromCharge(0, 'debit', 'instant')['fee']);
    }

    public function test_endpoint_calculates_validates_and_requires_a_store_owner(): void
    {
        $store = $this->store();
        $query = ['mode' => 'receive', 'amount' => 25, 'method' => 'debit', 'release' => 'instant'];

        $this->get(route('dashboard.productos.fee-simulator', $query))->assertRedirect();

        $this->actingAs($store->user);
        $this->getJson(route('dashboard.productos.fee-simulator', $query))->assertOk()
            ->assertJsonPath('charge', 27.3)->assertJsonPath('net', 25)->assertJsonPath('fee', 2.3);
        $this->getJson(route('dashboard.productos.fee-simulator', ['method' => 'crypto'] + $query))->assertStatus(422);
        $this->getJson(route('dashboard.productos.fee-simulator', ['mode' => 'nope'] + $query))->assertStatus(422);
    }

    public function test_the_product_form_shows_the_simulator_only_to_mercado_pago_stores(): void
    {
        $mp = $this->store();
        $this->actingAs($mp->user)->get(route('dashboard.productos.create'))->assertOk()
            ->assertSee('Simulador de comisiones')->assertSee('WhatsApp o pago directo');

        $product = Product::create(['store_id' => $mp->id, 'name' => 'Polo', 'slug' => 'polo', 'price' => 50, 'is_active' => true]);
        $this->get(route('dashboard.productos.edit', $product))->assertOk()->assertSee('Simulador de comisiones');

        $other = $this->store(['payment_gateway' => 'paypal']);
        $this->actingAs($other->user)->get(route('dashboard.productos.create'))->assertOk()->assertDontSee('Simulador de comisiones');
    }
}
