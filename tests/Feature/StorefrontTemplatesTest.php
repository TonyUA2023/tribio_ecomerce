<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Services\Storefront\TemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        if (config('database.default') !== 'sqlite') {
            return [];
        }
        // The legacy role conversion uses MySQL-only MODIFY syntax. These tests
        // use the original store_owner role and keep the production migrations intact.
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function storeFor(string $template, array $attributes = []): Store
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $slug = $attributes['slug'] ?? 'tienda-' . $owner->id;

        return Store::create($attributes + [
            'user_id' => $owner->id, 'name' => 'Tienda ' . $owner->id, 'slug' => $slug,
            'status' => 'active', 'template_name' => $template,
        ]);
    }

    private function lockedMaetekLikeStore(): Store
    {
        $store = $this->storeFor('minimal-light', ['name' => 'Maetek', 'slug' => 'maetek-store']);
        $store->forceFill(['template_locked' => true])->save();

        return $store;
    }

    public function test_catalog_lists_available_and_upcoming_designs_but_never_the_private_bespoke_one(): void
    {
        $store = $this->storeFor('elegant-dark');

        $this->actingAs($store->user)->get(route('dashboard.plantillas.index'))
            ->assertOk()
            ->assertSee('dashboard-navigation')
            ->assertSee('Soft Market')
            ->assertSee('Próximamente')
            ->assertSee('Industrial Light')
            ->assertSee(route('dashboard.plantillas.preview', 'soft-market'))
            ->assertDontSee('Minimal Light');
    }

    public function test_owner_applies_an_available_template_and_lands_in_the_customizer(): void
    {
        $store = $this->storeFor('elegant-dark');

        $this->actingAs($store->user)->post(route('dashboard.plantillas.apply', 'soft-market'))
            ->assertRedirect(route('dashboard.plantillas.customize'));

        $this->assertSame('soft-market', $store->fresh()->template_name);
        $this->get(route('dashboard.plantillas.customize'))->assertOk()
            ->assertSee('Colores y tipografía')
            ->assertSee('Portada (hero)')
            ->assertSee('Publicar');
    }

    public function test_unfinished_private_or_unknown_templates_cannot_be_applied(): void
    {
        $store = $this->storeFor('elegant-dark');
        $this->actingAs($store->user);

        foreach (['industrial-light', 'minimal-light', 'no-existe'] as $template) {
            $this->post(route('dashboard.plantillas.apply', $template))->assertSessionHasErrors('template');
        }
        $this->post('/dashboard/plantillas/Mala_Clave/aplicar')->assertNotFound();

        $this->assertSame('elegant-dark', $store->fresh()->template_name);
    }

    public function test_locked_bespoke_store_is_fully_isolated_from_the_module(): void
    {
        $store = $this->lockedMaetekLikeStore();
        $this->actingAs($store->user);

        $this->get(route('dashboard.plantillas.index'))->assertOk()
            ->assertSee('diseño hecho a medida')
            ->assertDontSee('Usar plantilla')
            ->assertDontSee(route('dashboard.plantillas.frame', 'soft-market'));
        $this->post(route('dashboard.plantillas.apply', 'soft-market'))->assertRedirect(route('dashboard.plantillas.index'));
        $this->get(route('dashboard.plantillas.customize'))->assertRedirect(route('dashboard.plantillas.index'));
        $this->get(route('dashboard.plantillas.preview', 'soft-market'))->assertRedirect(route('dashboard.plantillas.index'));
        $this->get(route('dashboard.plantillas.frame', 'soft-market'))->assertNotFound();
        $this->put(route('dashboard.plantillas.update'), ['settings' => ['hero' => ['title' => 'Hack']]])->assertForbidden();
        $this->delete(route('dashboard.plantillas.reset'))->assertRedirect(route('dashboard.plantillas.index'));

        $store->refresh();
        $this->assertSame('minimal-light', $store->template_name);
        $this->assertNull($store->template_settings);
    }

    public function test_lock_is_not_mass_assignable(): void
    {
        $store = $this->storeFor('soft-market', ['template_locked' => true]);
        $this->assertFalse($store->fresh()->template_locked);

        $store->update(['template_locked' => true]);
        $this->assertFalse($store->fresh()->template_locked);
    }

    public function test_migration_locks_every_store_already_on_the_bespoke_minimal_light_build(): void
    {
        $maetek = $this->storeFor('minimal-light', ['slug' => 'maetek-store']);
        $other = $this->storeFor('industrial-light');

        $migration = require database_path('migrations/2026_09_22_120000_add_template_settings_to_stores_table.php');
        $migration->down();
        $migration->up();

        $this->assertTrue($maetek->fresh()->template_locked);
        $this->assertFalse($other->fresh()->template_locked);
    }

    public function test_bespoke_minimal_light_ignores_any_template_settings(): void
    {
        $store = $this->storeFor('minimal-light', ['slug' => 'maetek-store']);
        $store->forceFill(['template_settings' => [
            'minimal-light' => ['hero' => ['title' => 'NO DEBE APARECER']],
            'soft-market' => ['hero' => ['title' => 'TAMPOCO ESTE'], 'colors' => ['primary' => '#123456']],
        ]])->save();

        $this->get(route('store.show', $store->slug))->assertOk()
            ->assertSee('Innovación que inspira tu estilo de vida')
            ->assertDontSee('NO DEBE APARECER')
            ->assertDontSee('TAMPOCO ESTE')
            ->assertDontSee('--t-primary', false);

        foreach (glob(resource_path('views/templates/minimal-light/*.blade.php')) as $view) {
            $this->assertStringNotContainsString('storefrontTheme', file_get_contents($view), basename($view) . ' must stay frozen');
        }
    }

    public function test_owner_customization_is_saved_and_rendered_on_the_storefront(): void
    {
        $store = $this->storeFor('soft-market');

        $this->actingAs($store->user)->put(route('dashboard.plantillas.update'), ['settings' => [
            'colors' => ['primary' => '#1e40af', 'secondary' => '#F2C14E', 'background' => 'white'],
            'typography' => ['heading' => 'playfair'],
            'announcement' => ['enabled' => '0', 'text' => 'Anuncio oculto'],
            'hero' => ['title' => 'Hola <script>alert(1)</script>', 'badge' => '', 'show_store_name' => '1'],
            'pillars' => ['items' => [['title' => 'Envío gratis']]],
        ]])->assertSessionHasNoErrors()->assertRedirect(route('dashboard.plantillas.customize'));

        $settings = $store->fresh()->template_settings['soft-market'];
        $this->assertSame('#1E40AF', $settings['colors']['primary']);
        $this->assertSame('playfair', $settings['typography']['heading']);
        $this->assertFalse($settings['announcement']['enabled']);
        $this->assertArrayNotHasKey('badge', $settings['hero'], 'A cleared field must fall back to the default');
        $this->assertArrayNotHasKey('enabled', $settings['pillars'], 'An omitted toggle keeps its default instead of turning off');

        $this->get(route('store.show', $store->slug))->assertOk()
            ->assertSee('--t-primary: #1E40AF', false)
            ->assertSee('--t-bg: #FFFFFF', false)
            ->assertSee('Playfair Display', false)
            ->assertSee('Hola &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('data-tpl-text="pillars.items.0.title">Envío gratis</h4>', false)
            ->assertSee('data-tpl-text="hero.badge">Productos exclusivos</span>', false)
            ->assertDontSee('Anuncio oculto');
    }

    public function test_invalid_settings_are_rejected_without_saving(): void
    {
        $store = $this->storeFor('soft-market');

        $this->actingAs($store->user)->put(route('dashboard.plantillas.update'), ['settings' => [
            'colors' => ['primary' => 'red', 'background' => 'neon'],
            'typography' => ['heading' => 'comic-sans'],
            'hero' => ['title' => str_repeat('x', 91)],
        ]])->assertSessionHasErrors(['settings.colors.primary', 'settings.colors.background', 'settings.typography.heading', 'settings.hero.title']);

        $this->assertNull($store->fresh()->template_settings);
    }

    public function test_saving_one_template_keeps_other_templates_settings_and_reset_restores_defaults(): void
    {
        $store = $this->storeFor('soft-market');
        $store->forceFill(['template_settings' => ['otra-plantilla' => ['hero' => ['title' => 'Guardado antes']]]])->save();
        $this->actingAs($store->user);

        $this->put(route('dashboard.plantillas.update'), ['settings' => ['hero' => ['title' => 'Nuevo título']]])->assertSessionHasNoErrors();
        $settings = $store->fresh()->template_settings;
        $this->assertSame('Guardado antes', $settings['otra-plantilla']['hero']['title']);
        $this->assertSame('Nuevo título', $settings['soft-market']['hero']['title']);

        $this->delete(route('dashboard.plantillas.reset'))->assertRedirect(route('dashboard.plantillas.customize'));
        $settings = $store->fresh()->template_settings;
        $this->assertArrayNotHasKey('soft-market', $settings);
        $this->assertSame('Guardado antes', $settings['otra-plantilla']['hero']['title']);
        $this->get(route('store.show', $store->slug))->assertSee('Descubre productos pensados para ti');
    }

    public function test_preview_frame_renders_any_available_design_without_side_effects(): void
    {
        $store = $this->storeFor('elegant-dark', ['total_views' => 5]);

        $response = $this->actingAs($store->user)->get(route('dashboard.plantillas.frame', 'soft-market'))->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertSee('tribio:template-preview', false)
            ->assertSee('data-tpl-text="hero.title"', false)
            ->assertDontSee('translate.google.com', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $store->refresh();
        $this->assertSame('elegant-dark', $store->template_name);
        $this->assertSame(5, (int) $store->total_views);

        $this->get(route('dashboard.plantillas.frame', 'industrial-light'))->assertNotFound();
        $this->get(route('dashboard.plantillas.preview', 'soft-market'))->assertOk()->assertSee('Usar');
    }

    public function test_soft_market_never_falls_back_to_another_stores_contact_details(): void
    {
        $store = $this->storeFor('soft-market');

        foreach ([route('store.show', $store->slug), route('store.contact', $store->slug)] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('maetek', false)
                ->assertDontSee('51956183384', false)
                ->assertDontSee('Lima, PE', false);
        }
    }

    public function test_contact_page_uses_the_templates_own_view_or_the_safe_fallback(): void
    {
        $this->get(route('store.contact', $this->storeFor('minimal-light')->slug))->assertViewIs('templates.minimal-light.contact');
        $this->get(route('store.contact', $this->storeFor('soft-market')->slug))->assertViewIs('templates.soft-market.contact');
        $this->get(route('store.contact', $this->storeFor('industrial-light')->slug))->assertViewIs('templates.soft-market.contact');
    }

    public function test_store_settings_defers_hero_copy_to_the_module_only_for_customizable_templates(): void
    {
        $soft = $this->storeFor('soft-market');
        $this->actingAs($soft->user)->get(route('dashboard.store.edit'))->assertOk()
            ->assertSee('Personalizar portada')
            ->assertDontSee('name="hero_title"', false);

        $maetek = $this->lockedMaetekLikeStore();
        $this->actingAs($maetek->user)->get(route('dashboard.store.edit'))->assertOk()
            ->assertSee('name="hero_title"', false);
    }

    public function test_every_published_template_ships_every_page_and_new_stores_get_one(): void
    {
        $registry = app(TemplateRegistry::class);

        foreach ($registry->all() as $key => $template) {
            if (($template['status'] ?? null) === TemplateRegistry::STATUS_AVAILABLE) {
                $this->assertSame([], $registry->missingViews($key), "{$key} is published but incomplete");
            }
        }
        $this->assertTrue($registry->isSelectable(config('storefront.default_template')));
        $this->assertSame('private', $registry->find('minimal-light')['status']);
    }
}
