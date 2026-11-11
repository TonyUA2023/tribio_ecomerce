<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** tribio:import-products: a client's inventory (CSV) into a store. */
class ImportProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function csv(string $body): string
    {
        $path = sys_get_temp_dir() . '/import-' . uniqid() . '.csv';
        file_put_contents($path, "\xEF\xBB\xBF" . $body);

        return $path;
    }

    private function store(): Store
    {
        $owner = User::factory()->create(['role' => 'store_owner']);

        return Store::create(['user_id' => $owner->id, 'name' => 'El Yunque', 'slug' => 'el-yunque', 'status' => 'active', 'template_name' => 'soft-market', 'mode' => Store::MODE_BOTH]);
    }

    public function test_imports_stock_cost_location_wholesale_category_and_brand_and_can_run_twice(): void
    {
        $store = $this->store();
        $file = $this->csv(
            "nombre,stock,ubicacion,costo,precio,precio_x6,categoria,marca,activo,nota\n"
            . "FOCO LED 9 WTS OPALUX,108,TORRE A - 3,3.5,6,4.5,Iluminación,Opalux,1,\n"
            . "FOCO LED 12 WTS OPALUX,,TORRE A - 3,4.5,8,9,Iluminación,Opalux,1,\n"
            . "CANDADO 70MM TRAVEX,0,TORRE C - 6,,0,,Cerrajería,Travex,0,SIN PRECIO\n"
        );

        $this->artisan('tribio:import-products', ['store' => 'el-yunque', 'file' => $file, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Product::count(), 'the dry run saves nothing');

        $this->artisan('tribio:import-products', ['store' => 'el-yunque', 'file' => $file])->assertSuccessful();
        $this->assertSame(3, Product::count());

        $foco = Product::where('name', 'FOCO LED 9 WTS OPALUX')->firstOrFail();
        $this->assertSame([108, 3.5, 6.0, 'TORRE A - 3', 'Iluminación', 'Opalux', true], [$foco->stock, (float) $foco->cost_price, (float) $foco->price, $foco->location, $foco->category->name, $foco->brand->name, $foco->is_active]);
        $this->assertSame([['min_qty' => 6, 'price' => 4.5]], $foco->price_tiers);
        $this->assertStringStartsWith('ELY-', $foco->sku);
        $this->assertDatabaseHas('inventory_movements', ['product_id' => $foco->id, 'quantity' => 108, 'type' => 'in']);

        $noStock = Product::where('name', 'FOCO LED 12 WTS OPALUX')->firstOrFail();
        $this->assertSame(0, $noStock->stock);
        $this->assertNull($noStock->price_tiers, 'a wholesale price that is not lower than the price is ignored');

        $candado = Product::where('name', 'CANDADO 70MM TRAVEX')->firstOrFail();
        $this->assertFalse($candado->is_active, 'a row without price stays off until the owner prices it');
        $this->assertSame(2, $store->categories()->count());
        $this->assertSame(2, \App\Models\Brand::where('store_id', $store->id)->count());

        // Running it again does not duplicate anything.
        $this->artisan('tribio:import-products', ['store' => 'el-yunque', 'file' => $file])->assertSuccessful();
        $this->assertSame(3, Product::count());
    }

    public function test_a_bad_row_saves_nothing_and_a_semicolon_file_works(): void
    {
        $this->store();
        $bad = $this->csv("nombre;precio\nMARTILLO;12\nCLAVO;abc\n");
        $this->artisan('tribio:import-products', ['store' => 'el-yunque', 'file' => $bad])->assertFailed();
        $this->assertSame(0, Product::count());

        $ok = $this->csv("nombre;precio;stock\nMARTILLO;12,50;3\n");
        $this->artisan('tribio:import-products', ['store' => 'el-yunque', 'file' => $ok])->assertSuccessful();
        $this->assertSame([12.5, 3], [(float) Product::sole()->price, Product::sole()->stock]);

        $this->artisan('tribio:import-products', ['store' => 'nadie', 'file' => $ok])->assertFailed();
    }
}
