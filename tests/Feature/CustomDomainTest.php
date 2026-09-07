<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_domain_resolves_store(): void
    {
        // 1. Crear un usuario y una tienda activa con dominio personalizado
        $user = User::factory()->create();
        $store = Store::create([
            'user_id' => $user->id,
            'name' => 'Tienda de Prueba',
            'slug' => 'tienda-prueba',
            'status' => 'active',
            'custom_domain' => 'mitienda.test',
            'template_name' => 'industrial-light',
        ]);

        // 2. Probar acceso vía dominio principal
        // Debería poder verse en la subruta /tienda/tienda-prueba
        $response = $this->get('/tienda/tienda-prueba');
        $response->assertStatus(200);

        // 3. Probar acceso vía dominio personalizado (mitienda.test)
        $responseDomain = $this->get('http://mitienda.test/');
        $responseDomain->assertStatus(200);

        // 4. Probar navegación interna reescrita
        // El catálogo debería estar accesible en http://mitienda.test/catalogo
        $responseCatalog = $this->get('http://mitienda.test/catalogo');
        $responseCatalog->assertStatus(200);

        // 5. Probar vista de producto
        $product = \App\Models\Product::create([
            'store_id' => $store->id,
            'category_id' => \App\Models\Category::create(['store_id' => $store->id, 'name' => 'Cat', 'slug' => 'cat'])->id,
            'name' => 'Producto 1',
            'slug' => 'producto-1',
            'price' => 100,
            'stock' => 10,
            'is_active' => true,
        ]);
        $responseProduct = $this->get('http://mitienda.test/producto/producto-1');
        $responseProduct->assertStatus(200);

        // 6. Probar colisión de slugs entre diferentes tiendas
        $store2 = Store::create([
            'user_id' => $user->id,
            'name' => 'Tienda 2',
            'slug' => 'tienda-2',
            'status' => 'active',
            'custom_domain' => 'tienda2.test',
        ]);
        $product2 = \App\Models\Product::create([
            'store_id' => $store2->id,
            'category_id' => \App\Models\Category::create(['store_id' => $store2->id, 'name' => 'Cat 2', 'slug' => 'cat-2'])->id,
            'name' => 'Producto 1 (Store 2)',
            'slug' => 'producto-1',
            'price' => 200,
            'stock' => 5,
            'is_active' => true,
        ]);
        
        $responseProduct2 = $this->get('http://tienda2.test/producto/producto-1');
        $responseProduct2->assertStatus(200); // Esto fallará si el Route Binding devuelve el $product de la tienda 1.
    }

    public function test_custom_domain_redirects_admin_routes(): void
    {
        $user = User::factory()->create();
        $store = Store::create([
            'user_id' => $user->id,
            'name' => 'Tienda de Prueba',
            'slug' => 'tienda-prueba',
            'status' => 'active',
            'custom_domain' => 'mitienda.test',
            'template_name' => 'industrial-light',
        ]);

        // Probar que el acceso a /login en el dominio propio redirige al dominio principal
        $response = $this->get('http://mitienda.test/login');
        $response->assertRedirect(rtrim(config('app.url'), '/') . '/login');

        // Probar que el acceso a /dashboard en el dominio propio redirige al dominio principal
        $responseDashboard = $this->get('http://mitienda.test/dashboard');
        $responseDashboard->assertRedirect(rtrim(config('app.url'), '/') . '/dashboard');
    }
}
