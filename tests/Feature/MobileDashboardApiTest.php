<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsReviewFixtures;
use Tests\TestCase;

/** The mobile seller API: parity with the web dashboard, tenant isolation and no secret leaks. */
class MobileDashboardApiTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReviewFixtures;

    protected function migrateFreshUsing(): array
    {
        if (config('database.default') !== 'sqlite') {
            return [];
        }

        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function seller(array $storeAttributes = []): array
    {
        $store = $this->makeStore($storeAttributes);
        Sanctum::actingAs($store->user, ['*']);

        return [$store->user, $store];
    }

    public function test_store_settings_never_expose_payment_credentials(): void
    {
        [, $store] = $this->seller(['mp_access_token' => 'APP_USR-SECRET-TOKEN', 'paypal_client_secret' => 'PAYPAL-SECRET', 'flow_api_key' => 'FLOWKEY']);

        $body = $this->getJson('/api/dashboard/tienda')->assertOk()->assertJsonPath('store.id', $store->id)->getContent();
        $this->assertStringNotContainsString('APP_USR-SECRET-TOKEN', $body);
        $this->assertStringNotContainsString('PAYPAL-SECRET', $body);
        $this->assertStringNotContainsString('FLOWKEY', $body);

        $user = $this->getJson('/api/user')->assertOk()->getContent();
        $this->assertStringNotContainsString('APP_USR-SECRET-TOKEN', $user);
    }

    public function test_store_settings_update_normalizes_like_the_web_form(): void
    {
        [, $store] = $this->seller();

        $this->putJson('/api/dashboard/tienda', [
            'name' => 'Nombre Nuevo',
            'custom_domain' => 'https://MiTienda.com/inicio',
            'national_shipping_cost' => '',
            'enabled_countries' => ['US'],
            'country_shipping_costs' => ['us' => '12.5', 'MX' => ''],
            'free_shipping_min_amount' => 150,
        ])->assertOk()->assertJsonPath('store.custom_domain', 'mitienda.com');

        $store->refresh();
        $this->assertSame('nombre-nuevo', $store->slug);
        $this->assertNull($store->national_shipping_cost);          // empty stays NULL, not 0
        $this->assertEqualsCanonicalizing(['US', 'PE'], $store->enabled_countries); // Peru is always sold
        $this->assertSame(['US' => 12.5], $store->country_shipping_costs);
    }

    public function test_store_settings_reject_a_domain_used_by_another_store(): void
    {
        $this->makeStore(['custom_domain' => 'ocupado.com']);
        $this->seller();

        $this->putJson('/api/dashboard/tienda', ['custom_domain' => 'ocupado.com'])
            ->assertStatus(422)->assertJsonValidationErrors('custom_domain');
    }

    public function test_switching_only_works_between_the_accounts_own_stores(): void
    {
        [$user, $first] = $this->seller();
        $second = Store::create(['user_id' => $user->id, 'name' => 'Segunda', 'slug' => 'segunda', 'status' => 'active']);
        $foreign = $this->makeStore();

        $this->getJson('/api/dashboard/tiendas')->assertOk()->assertJsonCount(2, 'stores');
        $this->postJson("/api/dashboard/tiendas/{$second->id}/cambiar")->assertOk();
        $this->assertSame($second->id, $user->fresh()->currentStore()->id);
        $this->postJson("/api/dashboard/tiendas/{$foreign->id}/cambiar")->assertNotFound();
    }

    public function test_category_crud_enforces_the_header_limit_and_tenant_scope(): void
    {
        [, $store] = $this->seller();
        $foreign = Category::create(['store_id' => $this->makeStore()->id, 'name' => 'Ajena', 'slug' => 'ajena']);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/dashboard/categorias', ['name' => "Cat {$i}", 'show_in_header' => true])->assertCreated();
        }
        $this->postJson('/api/dashboard/categorias', ['name' => 'Sexta', 'show_in_header' => true])
            ->assertStatus(422)->assertJsonValidationErrors('show_in_header');
        $sixth = $this->postJson('/api/dashboard/categorias', ['name' => 'Sexta'])->assertCreated()->json('category.id');
        $this->postJson("/api/dashboard/categorias/{$sixth}/encabezado")->assertStatus(422);

        $this->getJson('/api/dashboard/categorias')->assertOk()->assertJsonPath('header_count', 5)->assertJsonCount(6, 'data');
        $this->putJson("/api/dashboard/categorias/{$foreign->id}", ['name' => 'Hackeada'])->assertNotFound();
        $this->deleteJson("/api/dashboard/categorias/{$foreign->id}")->assertNotFound();
        $this->assertSame('Ajena', $foreign->fresh()->name);
        $this->assertSame(6, $store->categories()->count());
    }

    public function test_gateway_secrets_are_write_only_and_blank_keeps_the_saved_one(): void
    {
        [, $store] = $this->seller();

        $this->putJson('/api/dashboard/pasarela', [
            'checkout_mode' => 'card', 'payment_gateway' => 'mercado_pago',
            'mp_access_token' => 'APP_USR-1234567890-ABCD', 'mp_public_key' => 'APP_USR-public',
        ])->assertOk()
            ->assertJsonPath('gateway.mercado_pago.has_access_token', true)
            ->assertJsonPath('gateway.mercado_pago.token_hint', '••••ABCD');

        // Saving again without the token must not wipe it.
        $response = $this->putJson('/api/dashboard/pasarela', ['checkout_mode' => 'card', 'payment_gateway' => 'mercado_pago', 'mp_public_key' => 'APP_USR-public'])->assertOk();
        $this->assertStringNotContainsString('1234567890', $response->getContent());
        $this->assertSame('APP_USR-1234567890-ABCD', $store->fresh()->mp_access_token);

        $this->putJson('/api/dashboard/pasarela', ['checkout_mode' => 'card', 'payment_gateway' => 'flow'])
            ->assertStatus(422)->assertJsonValidationErrors('payment_gateway');
    }

    public function test_shipping_rates_are_created_validated_and_scoped(): void
    {
        [, $store] = $this->seller();
        $foreignRate = $this->makeStore()->shippingRates()->create(['country_code' => 'PE', 'cost' => 9, 'is_active' => true]);

        $id = $this->postJson('/api/dashboard/envios', ['country_code' => 'pe', 'state' => 'Lima', 'cost' => 12])
            ->assertCreated()->assertJsonPath('rate.country_code', 'PE')->json('rate.id');
        $this->postJson('/api/dashboard/envios', ['country_code' => 'ZZ', 'cost' => 1])->assertStatus(422);
        $this->getJson('/api/dashboard/envios')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/dashboard/envios/{$foreignRate->id}")->assertNotFound();
        $this->deleteJson("/api/dashboard/envios/{$id}")->assertOk();
        $this->assertSame(0, $store->shippingRates()->count());
    }

    public function test_inventory_adjustment_records_a_movement(): void
    {
        [, $store] = $this->seller();
        $product = $this->makeProduct($store, ['track_stock' => true, 'stock' => 10, 'low_stock_alert' => 3]);

        $this->postJson("/api/dashboard/inventario/{$product->id}/ajuste", ['type' => 'out', 'quantity' => 4, 'reason' => 'Merma'])
            ->assertOk()->assertJsonPath('product.stock', 6);
        $this->postJson("/api/dashboard/inventario/{$product->id}/ajuste", ['type' => 'adjustment', 'quantity' => 2])
            ->assertOk()->assertJsonPath('product.stock', 2);

        $this->getJson('/api/dashboard/inventario?filter=low')->assertOk()->assertJsonPath('stats.low_stock', 1)->assertJsonCount(1, 'data');
        $this->getJson("/api/dashboard/inventario/{$product->id}")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_review_reply_and_visibility_only_for_the_owners_store(): void
    {
        [, $store] = $this->seller();
        $mine = $this->seedReview($this->makeProduct($store), 4, 'Bien');
        $other = $this->seedReview($this->makeProduct($this->makeStore()), 1, 'Mal');

        $this->getJson('/api/dashboard/resenas')->assertOk()->assertJsonPath('stats.total', 1)->assertJsonPath('stats.unanswered', 1);
        $this->putJson("/api/dashboard/resenas/{$mine->id}/respuesta", ['reply' => '¡Gracias!'])->assertOk()->assertJsonPath('review.store_reply', '¡Gracias!');
        $this->patchJson("/api/dashboard/resenas/{$mine->id}/visibilidad")->assertOk()->assertJsonPath('review.is_published', false);
        $this->patchJson("/api/dashboard/resenas/{$other->id}/visibilidad")->assertNotFound();
        $this->putJson("/api/dashboard/resenas/{$other->id}/respuesta", ['reply' => 'x'])->assertNotFound();
    }

    public function test_product_can_be_created_with_the_full_field_set_and_variants(): void
    {
        [, $store] = $this->seller();
        $category = Category::create(['store_id' => $store->id, 'name' => 'Polos', 'slug' => 'polos']);

        $id = $this->postJson('/api/dashboard/productos', [
            'name' => 'Polo Bordado', 'price' => 50, 'compare_price' => 70, 'stock' => 9, 'track_stock' => 1,
            'low_stock_alert' => 2, 'is_new' => 1, 'tags' => 'polo, bordado', 'categories' => json_encode([$category->id]),
            'has_variants' => 1, 'variant_options_json' => json_encode([['name' => 'Talla', 'values' => ['S', 'M']]]),
            'variants_json' => json_encode([['sku' => 'P-S', 'stock' => 4, 'attributes' => ['Talla' => 'S']], ['sku' => 'P-M', 'stock' => 5, 'attributes' => ['Talla' => 'M']]]),
        ])->assertCreated()->json('product.id');

        $product = Product::findOrFail($id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame(['polo', 'bordado'], $product->tags);
        $this->assertSame(2, $product->variants()->count());
        $this->assertNotEmpty($product->sku);
        $this->assertSame(1, $product->inventoryMovements()->count());   // initial stock movement

        // A partial update (the list's visibility switch) leaves everything else alone.
        $this->putJson("/api/dashboard/productos/{$id}", ['is_active' => 0])->assertOk();
        $product->refresh();
        $this->assertFalse((bool) $product->is_active);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(['polo', 'bordado'], $product->tags);
    }

    public function test_order_list_carries_status_counts_and_manual_payments_settle_the_balance(): void
    {
        [, $store] = $this->seller();
        $product = $this->makeProduct($store);
        $buyer = $this->buyer();
        $order = $this->purchase($buyer, $product, 'pending', ['payment_status' => 'pending', 'total' => 100, 'production_stage' => 'received']);
        $this->purchase($buyer, $product, 'delivered');

        $this->getJson('/api/dashboard/pedidos')->assertOk()
            ->assertJsonPath('status_counts.pending', 1)->assertJsonPath('status_counts.delivered', 1)->assertJsonPath('total_count', 2);

        $this->postJson("/api/dashboard/pedidos/{$order->id}/pagos", ['amount' => 40, 'method' => 'yape'])
            ->assertOk()->assertJsonPath('order.payment_status', 'partial');
        $this->postJson("/api/dashboard/pedidos/{$order->id}/pagos", ['amount' => 999, 'method' => 'yape'])->assertStatus(422); // more than owed
        $this->postJson("/api/dashboard/pedidos/{$order->id}/pagos", ['amount' => 60, 'method' => 'efectivo'])
            ->assertOk()->assertJsonPath('order.payment_status', 'paid');

        $this->patchJson("/api/dashboard/pedidos/{$order->id}/produccion", ['production_stage' => 'in_production'])
            ->assertOk()->assertJsonPath('production_stage', 'in_production');
        $this->getJson("/api/dashboard/pedidos/{$order->id}")->assertOk()
            ->assertJsonPath('is_made_to_order', true)->assertJsonCount(2, 'payments');
    }

    public function test_another_stores_order_cannot_be_touched(): void
    {
        $this->seller();
        $foreignStore = $this->makeStore();
        $foreign = $this->purchase($this->buyer(), $this->makeProduct($foreignStore), 'pending', ['production_stage' => 'received']);

        $this->getJson("/api/dashboard/pedidos/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/dashboard/pedidos/{$foreign->id}/pagos", ['amount' => 1, 'method' => 'yape'])->assertForbidden();
        $this->patchJson("/api/dashboard/pedidos/{$foreign->id}/produccion", ['production_stage' => 'ready'])->assertForbidden();
    }

    public function test_marketing_meta_saves_parsed_ids_and_never_returns_the_token(): void
    {
        [, $store] = $this->seller();
        $token = 'EAAB' . str_repeat('x', 40) . 'Q9zT';

        $response = $this->putJson('/api/dashboard/marketing/meta', [
            'is_active' => true, 'pixel_id' => "fbq('init', '1234567890123')", 'capi_token' => $token, 'default_condition' => 'new',
        ])->assertOk()->assertJsonPath('integration.pixel_id', '1234567890123')->assertJsonPath('integration.has_capi_token', true);
        $this->assertStringNotContainsString($token, $response->getContent());

        $this->getJson('/api/dashboard/marketing/meta')->assertOk()->assertJsonPath('integration.capi_token_hint', 'EAAB…Q9zT');
        $this->getJson('/api/dashboard/marketing')->assertOk()->assertJsonPath('meta.is_active', true);
    }

    public function test_password_change_needs_the_current_password(): void
    {
        [$user] = $this->seller();
        $user->forceFill(['password' => 'password-actual'])->save();

        $this->putJson('/api/dashboard/cuenta/contrasena', ['current_password' => 'mala', 'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->putJson('/api/dashboard/cuenta/contrasena', ['current_password' => 'password-actual', 'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1'])
            ->assertOk();
    }

    public function test_wholesale_tiers_are_saved_validated_and_returned(): void
    {
        [, $store] = $this->seller();

        $id = $this->postJson('/api/dashboard/productos', [
            'name' => 'Polo DTF', 'price' => 30, 'stock' => 50, 'track_stock' => 1,
            'price_tiers_json' => json_encode([['min_qty' => 6, 'price' => 25], ['min_qty' => 12, 'price' => 20]]),
            'min_quantity' => 3,
        ])->assertCreated()->json('product.id');

        $product = Product::findOrFail($id);
        $this->assertEquals([['min_qty' => 6, 'price' => 25], ['min_qty' => 12, 'price' => 20]], $product->price_tiers);
        $this->assertSame(3, $product->min_quantity);

        // A tier that is not cheaper than the previous one is rejected and nothing is half-saved.
        $this->putJson("/api/dashboard/productos/{$id}", ['price_tiers_json' => json_encode([['min_qty' => 6, 'price' => 35]])])
            ->assertStatus(422)->assertJsonValidationErrors('price_tiers');
        $this->assertCount(2, $product->fresh()->price_tiers);

        // Clearing them.
        $this->putJson("/api/dashboard/productos/{$id}", ['price_tiers_json' => '', 'min_quantity' => ''])->assertOk();
        $this->assertEmpty($product->fresh()->price_tiers);
        $this->getJson('/api/dashboard/tienda')->assertJsonPath('options.wholesale_available', true);
    }

    public function test_messages_inbox_filters_marks_read_and_is_tenant_scoped(): void
    {
        [, $store] = $this->seller();
        $mine = $store->contactMessages()->create(['name' => 'Rosa', 'email' => 'rosa@example.test', 'subject' => 'Consulta', 'message' => 'Hola, ¿hacen polos por docena?']);
        $store->contactMessages()->create(['name' => 'Luis', 'email' => 'luis@example.test', 'subject' => 'Libro de Reclamaciones · Queja', 'message' => 'Demoró.']);
        $foreign = $this->makeStore()->contactMessages()->create(['name' => 'X', 'email' => 'x@example.test', 'subject' => 'Consulta', 'message' => 'ajeno']);

        $this->getJson('/api/dashboard/stats')->assertJsonPath('stats.unread_messages', 2);
        $this->getJson('/api/dashboard/mensajes')->assertOk()->assertJsonPath('counts.total', 2)->assertJsonPath('counts.sin-leer', 2)->assertJsonPath('counts.libro', 1);
        $this->getJson('/api/dashboard/mensajes?filter=libro')->assertJsonCount(1, 'data');

        $this->getJson("/api/dashboard/mensajes/{$mine->id}")->assertOk()->assertJsonPath('message.body', 'Hola, ¿hacen polos por docena?');
        $this->assertTrue($mine->fresh()->is_read);
        $this->patchJson("/api/dashboard/mensajes/{$mine->id}/no-leido")->assertOk();
        $this->assertFalse($mine->fresh()->is_read);
        $this->getJson("/api/dashboard/mensajes/{$foreign->id}")->assertNotFound();
        $this->patchJson("/api/dashboard/mensajes/{$foreign->id}/no-leido")->assertNotFound();
    }

    public function test_web_and_api_share_the_same_product_rules(): void
    {
        [$user, $store] = $this->seller();
        $foreignCategory = Category::create(['store_id' => $this->makeStore()->id, 'name' => 'Ajena', 'slug' => 'ajena-x']);
        $foreignBrand = $this->makeStore()->brands()->create(['name' => 'Ajena', 'slug' => 'ajena-b']);

        // Same rejection on the mobile API...
        $this->postJson('/api/dashboard/productos', ['name' => 'A', 'price' => 10, 'stock' => 1, 'brand_id' => $foreignBrand->id])->assertStatus(422)->assertJsonValidationErrors('brand_id');
        $this->postJson('/api/dashboard/productos', ['name' => 'A', 'price' => 10, 'stock' => 1, 'category_id' => $foreignCategory->id])->assertStatus(422)->assertJsonValidationErrors('category_id');

        // ...and on the web form (a store can no longer attach another store's brand/category).
        $this->actingAs($user)->post(route('dashboard.productos.store'), ['name' => 'A', 'price' => 10, 'stock' => 1, 'brand_id' => $foreignBrand->id])->assertSessionHasErrors('brand_id');

        // Web-created product: composite lot data set on mobile survives a web edit.
        $id = $this->postJson('/api/dashboard/productos', ['name' => 'Lote', 'price' => 100, 'stock' => 1, 'is_composite' => 1, 'composite_type' => 'assembly'])->assertCreated()->json('product.id');
        $product = Product::findOrFail($id);
        $this->actingAs($user)->put(route('dashboard.productos.update', $product), ['name' => 'Lote editado', 'price' => 100, 'stock' => 1, 'track_stock' => 1, 'is_active' => 1])->assertRedirect();
        $product->refresh();
        $this->assertSame('Lote editado', $product->name);
        $this->assertTrue((bool) $product->is_composite);
        $this->assertSame('assembly', $product->composite_type);
    }

    public function test_api_partial_update_keeps_unsent_flags_but_a_web_full_form_resets_unchecked_ones(): void
    {
        [$user, $store] = $this->seller();
        $product = $this->makeProduct($store, ['is_featured' => true, 'is_new' => true, 'track_stock' => true, 'stock' => 5]);

        $this->putJson("/api/dashboard/productos/{$product->id}", ['is_active' => 0])->assertOk();
        $product->refresh();
        $this->assertFalse((bool) $product->is_active);
        $this->assertTrue((bool) $product->is_featured);      // untouched
        $this->assertSame(5, $product->stock);

        // HTML form semantics: an unchecked checkbox is simply absent → false.
        $this->actingAs($user)->put(route('dashboard.productos.update', $product), ['name' => $product->name, 'price' => 50, 'stock' => 5, 'is_active' => 1])->assertRedirect();
        $product->refresh();
        $this->assertTrue((bool) $product->is_active);
        $this->assertFalse((bool) $product->is_featured);
    }
}
