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
