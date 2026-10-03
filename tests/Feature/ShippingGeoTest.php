<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Services\Checkout\ShippingCostResolver;
use App\Services\Geo\GeoCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Department/country shipping zones resolved from one normalized geo catalog. */
class ShippingGeoTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function store(): Store
    {
        return Store::create([
            'user_id' => User::factory()->create()->id, 'name' => 'Tienda', 'slug' => 'tienda', 'status' => 'active',
            'template_name' => 'soft-market', 'national_shipping_cost' => null,
        ]);
    }

    public function test_state_endpoint_falls_back_to_peru_departments_when_api_is_down(): void
    {
        Cache::flush();
        Http::fake(['countriesnow.space/*' => Http::response('', 500)]);

        $states = $this->getJson('/api/geo/countries/pe/states')->assertOk()->json('data');

        $this->assertCount(25, $states);
        $this->assertContains('Cusco', $states);
        $this->assertContains('BR', array_column($this->getJson('/api/geo/countries')->json('data'), 'code'));
    }

    public function test_countries_and_states_come_from_the_world_api_and_are_cached(): void
    {
        Cache::flush();
        Http::fake(['countriesnow.space/*' => Http::response(['error' => false, 'data' => [
            ['name' => 'Brazil', 'iso2' => 'BR', 'states' => [['name' => 'Bahia', 'state_code' => 'BA'], ['name' => 'São Paulo', 'state_code' => 'SP']]],
            ['name' => 'Mexico', 'iso2' => 'MX', 'states' => [['name' => 'Jalisco'], ['name' => 'Yucatán State']]],
        ]])]);

        $this->getJson('/api/geo/countries/MX/states')->assertOk()->assertJsonPath('data', ['Jalisco', 'Yucatán']);
        $this->getJson('/api/geo/countries/br/states')->assertOk()->assertJsonPath('data', ['Bahia', 'São Paulo']);
        $countries = $this->getJson('/api/geo/countries')->assertOk()->json('data');

        $this->assertGreaterThan(200, count($countries));
        $firstEight = array_slice(array_column($countries, 'code'), 0, 8);
        sort($firstEight);
        $this->assertSame(['AR', 'CL', 'CO', 'EC', 'ES', 'MX', 'PE', 'US'], $firstEight, 'countries with a store currency come first');
        $this->assertContains('BR', array_column($countries, 'code'));
        Http::assertSentCount(1);
    }

    public function test_resolver_matches_department_ignoring_case_accents_and_prefixes(): void
    {
        Cache::flush();
        Http::fake(['countriesnow.space/*' => Http::response('', 500)]);
        $store = $this->store();
        $store->shippingRates()->create(['country_code' => 'PE', 'state' => 'Áncash', 'cost' => 22, 'is_active' => true]);
        $store->shippingRates()->create(['country_code' => 'PE', 'state' => 'Lima', 'cost' => 8, 'is_active' => true]);
        $store->shippingRates()->create(['country_code' => 'ALL', 'state' => null, 'cost' => 99, 'is_active' => true]);
        $resolver = app(ShippingCostResolver::class);

        $this->assertSame(22.0, $resolver->resolve($store, 'PE', 'ancash'));
        $this->assertSame(8.0, $resolver->resolve($store, 'PE', 'Departamento de LIMA'));
        $this->assertSame(99.0, $resolver->resolve($store, 'BR', 'Bahia'));
    }

    public function test_dashboard_saves_canonical_state_updates_instead_of_duplicating_and_rejects_unknown(): void
    {
        Cache::flush();
        Http::fake(['countriesnow.space/*' => Http::response('', 500)]);
        $store = $this->store();
        $this->actingAs($store->user);

        $this->post(route('dashboard.shipping.store'), ['country_code' => 'PE', 'state' => 'cusco', 'cost' => 15])->assertSessionHasNoErrors();
        $this->post(route('dashboard.shipping.store'), ['country_code' => 'PE', 'state' => 'CUSCO', 'cost' => 18])->assertSessionHasNoErrors();
        $this->post(route('dashboard.shipping.store'), ['country_code' => 'PE', 'state' => 'Atlantida', 'cost' => 1])->assertSessionHasErrors('state');

        $this->post(route('dashboard.shipping.store'), ['country_code' => 'BR', 'cost' => 40])->assertSessionHasNoErrors();
        $this->post(route('dashboard.shipping.store'), ['country_code' => 'ZZ', 'cost' => 40])->assertSessionHasErrors('country_code');

        $this->assertSame(2, $store->shippingRates()->count());
        $this->assertSame('Cusco', $store->shippingRates()->where('country_code', 'PE')->first()->state);
        $this->assertSame('18.00', (string) $store->shippingRates()->where('country_code', 'PE')->first()->cost);
    }

    public function test_shipping_cost_endpoint_echoes_the_normalized_destination(): void
    {
        Cache::flush();
        Http::fake(['countriesnow.space/*' => Http::response('', 500)]);
        $store = $this->store();
        $store->shippingRates()->create(['country_code' => 'PE', 'state' => 'Piura', 'cost' => 12, 'is_active' => true]);

        $this->getJson("/api/shipping-cost/{$store->slug}?country=pe&state=PIURA")
            ->assertOk()->assertJsonPath('cost', 12)->assertJsonPath('country', 'PE')->assertJsonPath('state', 'Piura');
    }
}
