<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\BrandKit;
use App\Models\MarketingAsset;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\AiMarketing\AiMarketingException;
use App\Services\AiMarketing\ModelResponse;
use App\Services\AiMarketing\TextModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Dashboard → Marketing → Contenido con IA (M1): brand kit, AI copy with real prices, flyers, credits. */
class AiContentTest extends TestCase
{
    use RefreshDatabase;

    private FakeTextModel $model;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new FakeTextModel();
        $this->app->instance(TextModel::class, $this->model);
    }

    private function owner(array $store = []): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create($store + [
            'user_id' => $user->id, 'name' => 'Nutri Vida', 'slug' => 'nutri-vida', 'status' => 'active',
            'template_name' => 'soft-market', 'whatsapp_phone' => '51 999 888 777', 'plan' => 'basic',
        ]);

        return [$user, $store];
    }

    private function product(Store $store, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Batido Vainilla', 'slug' => 'batido-vainilla', 'description' => '<p>Rico en <b>proteína</b></p>',
            'price' => 89.9, 'compare_price' => 110, 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function generate(User $user, array $payload)
    {
        return $this->actingAs($user)->postJson(route('dashboard.marketing.content.copy'), $payload + [
            'objective' => 'oferta', 'channels' => ['instagram'],
        ]);
    }

    public function test_page_renders_with_credits_and_brand_defaults(): void
    {
        [$user, $store] = $this->owner(['accent_color' => '#AA3366']);

        $this->actingAs($user)->get(route('dashboard.marketing.content.index'))
            ->assertOk()
            ->assertSee('Contenido con IA')
            ->assertSee('de 30 disponibles')
            ->assertSee('value="#aa3366"', false);
    }

    public function test_owner_saves_brand_kit(): void
    {
        [$user, $store] = $this->owner();

        $this->actingAs($user)->put(route('dashboard.marketing.content.brand'), [
            'tone' => 'premium', 'slogan' => 'Vive mejor', 'banned_words_text' => "milagro, cura\nbarato, milagro",
            'primary_color' => '#112233', 'secondary_color' => '#445566', 'show_whatsapp' => '1',
        ])->assertRedirect(route('dashboard.marketing.content.index'));

        $kit = BrandKit::firstWhere('store_id', $store->id);
        $this->assertSame('premium', $kit->tone);
        $this->assertSame(['milagro', 'cura', 'barato'], $kit->banned_words);
        $this->assertTrue($kit->show_whatsapp);
    }

    public function test_copy_uses_real_prices_and_spends_one_credit(): void
    {
        [$user, $store] = $this->owner();
        $product = $this->product($store);
        $this->model->items = [[
            'channel' => 'instagram',
            'text' => "¡{producto} a solo {precio}! Antes {precio_antes} ({descuento} menos). Pídelo: {whatsapp} {inventado}",
            'hashtags' => ['#batidos', 'vida sana!'], 'cta' => 'Escríbenos al {whatsapp}',
        ]];

        $response = $this->generate($user, ['product_id' => $product->id])->assertOk();

        $item = $response->json('items.0');
        $this->assertSame('¡Batido Vainilla a solo S/ 89.90! Antes S/ 110.00 (18% menos). Pídelo: 51 999 888 777', $item['text']);
        $this->assertSame(['batidos', 'vidasana'], $item['hashtags']);
        $this->assertSame([], $item['warnings']);
        $this->assertSame(29, $response->json('remaining'));

        // The prompt only offers placeholders and carries the brand rules, never the amounts.
        $this->assertStringContainsString('{precio_antes}', $this->model->lastPrompt);
        $this->assertStringNotContainsString('89.90', $this->model->lastPrompt);
        $this->assertStringContainsString('No hagas promesas de salud', $this->model->lastSystem);

        $log = AiUsageLog::firstWhere('store_id', $store->id);
        $this->assertSame(1, (int) $log->credits);
        $this->assertSame('ok', $log->status);
        $this->assertEqualsWithDelta((1000 * 4 + 500 * 20) / 1_000_000, (float) $log->cost_usd, 0.000001);
        $this->assertSame(1, MarketingAsset::where('store_id', $store->id)->where('kind', 'copy')->count());
    }

    public function test_invented_amounts_and_banned_words_are_flagged(): void
    {
        [$user, $store] = $this->owner();
        $product = $this->product($store, ['compare_price' => null]);
        BrandKit::create(['store_id' => $store->id, 'tone' => 'cercano', 'banned_words' => ['milagro']]);
        $this->model->items = [['channel' => 'instagram', 'text' => 'Efecto milagro por S/ 50 con 30% off', 'hashtags' => [], 'cta' => '']];

        $warnings = $this->generate($user, ['product_id' => $product->id])->assertOk()->json('items.0.warnings');

        $this->assertCount(3, $warnings);
        $this->assertStringContainsString('S/ 50', $warnings[0]);
        $this->assertStringContainsString('30%', $warnings[1]);
        $this->assertStringContainsString('milagro', $warnings[2]);
        // Without a previous price the model is not even offered a discount.
        $this->assertStringNotContainsString('{descuento}', $this->model->lastPrompt);
    }

    public function test_no_call_when_credits_are_exhausted(): void
    {
        [$user, $store] = $this->owner();
        AiUsageLog::create(['store_id' => $store->id, 'feature' => 'copy', 'provider' => 'anthropic', 'model' => 'x', 'credits' => 30, 'status' => 'ok']);

        $this->generate($user, [])->assertStatus(422)->assertJsonFragment(['message' => 'Usaste todos tus créditos de IA de este mes. Se renuevan el día 1, o mejora tu plan para tener más.']);
        $this->assertSame(0, $this->model->calls);
    }

    public function test_credits_from_last_month_do_not_count(): void
    {
        [$user, $store] = $this->owner();
        $old = AiUsageLog::create(['store_id' => $store->id, 'feature' => 'copy', 'provider' => 'anthropic', 'model' => 'x', 'credits' => 30, 'status' => 'ok']);
        $old->forceFill(['created_at' => now()->subMonthNoOverflow()->startOfMonth()])->save();

        $this->generate($user, [])->assertOk()->assertJsonPath('remaining', 29);
    }

    public function test_refusal_or_empty_answer_costs_no_credit(): void
    {
        [$user, $store] = $this->owner();
        $this->model->stopReason = 'refusal';

        $this->generate($user, [])->assertStatus(422);

        $log = AiUsageLog::firstWhere('store_id', $store->id);
        $this->assertSame('refused', $log->status);
        $this->assertSame(0, (int) $log->credits);
        $this->assertSame(0, MarketingAsset::count());
    }

    public function test_provider_failure_is_a_friendly_message(): void
    {
        [$user] = $this->owner();
        $this->model->fail = true;

        $this->generate($user, [])->assertStatus(422)->assertJsonFragment(['message' => 'El asistente de IA no respondió. Inténtalo de nuevo en unos minutos.']);
    }

    public function test_without_api_key_the_module_says_it_is_activating(): void
    {
        [$user] = $this->owner();
        $this->model->available = false;

        $this->generate($user, [])->assertStatus(422)->assertJsonFragment(['message' => 'Estamos activando el asistente de IA. Apenas esté listo podrás usarlo aquí.']);
        $this->assertSame(0, $this->model->calls);
    }

    public function test_cannot_use_another_stores_product(): void
    {
        [$user] = $this->owner();
        [, $other] = $this->owner(['slug' => 'otra', 'name' => 'Otra']);
        $foreign = $this->product($other);

        $this->generate($user, ['product_id' => $foreign->id])->assertNotFound();
        $this->assertSame(0, $this->model->calls);
    }

    public function test_channels_are_validated(): void
    {
        [$user] = $this->owner();

        $this->generate($user, ['channels' => ['instagram', 'facebook', 'tiktok', 'whatsapp_estado']])->assertStatus(422)->assertJsonValidationErrors('channels');
        $this->generate($user, ['channels' => ['myspace']])->assertStatus(422)->assertJsonValidationErrors('channels.0');
    }

    public function test_flyer_studio_offers_only_templates_the_product_supports(): void
    {
        [$user, $store] = $this->owner();
        $this->product($store);
        $this->product($store, ['name' => 'Té Verde', 'slug' => 'te-verde', 'compare_price' => null]);

        $products = collect($this->actingAs($user)->get(route('dashboard.marketing.content.flyer'))->assertOk()->viewData('products'));

        $this->assertSame(['oferta', 'destacado'], $products->firstWhere('name', 'Batido Vainilla')['templates']);
        $this->assertSame('S/ 110.00', $products->firstWhere('name', 'Batido Vainilla')['compare']);
        $this->assertSame(['destacado'], $products->firstWhere('name', 'Té Verde')['templates']);
    }

    public function test_flyer_png_is_saved_to_the_library(): void
    {
        Storage::fake('public');
        [$user, $store] = $this->owner();
        $product = $this->product($store);

        $this->actingAs($user)->postJson(route('dashboard.marketing.content.flyer.store'), [
            'image' => UploadedFile::fake()->image('flyer.png', 1080, 1350), 'product_id' => $product->id,
            'template' => 'oferta', 'format' => 'portrait',
        ])->assertOk()->assertJsonStructure(['url']);

        $asset = MarketingAsset::firstWhere('kind', 'flyer');
        $this->assertSame($store->id, $asset->store_id);
        Storage::disk('public')->assertExists($asset->file_path);
        $this->assertStringStartsWith("stores/{$store->id}/marketing/", $asset->file_path);
    }

    public function test_flyer_upload_rejects_wrong_files(): void
    {
        Storage::fake('public');
        [$user] = $this->owner();

        $this->actingAs($user)->postJson(route('dashboard.marketing.content.flyer.store'), [
            'image' => UploadedFile::fake()->image('tiny.png', 200, 200), 'template' => 'oferta', 'format' => 'square',
        ])->assertStatus(422)->assertJsonValidationErrors('image');
    }

    public function test_owner_cannot_delete_another_stores_asset(): void
    {
        [$user] = $this->owner();
        [, $other] = $this->owner(['slug' => 'otra', 'name' => 'Otra']);
        $asset = MarketingAsset::create(['store_id' => $other->id, 'kind' => 'copy', 'title' => 'x']);

        $this->actingAs($user)->delete(route('dashboard.marketing.content.assets.destroy', $asset))->assertNotFound();
        $this->assertModelExists($asset);
    }
}

/** Stands in for Claude: returns canned items and records what it was asked. */
class FakeTextModel implements TextModel
{
    public bool $available = true;
    public bool $fail = false;
    public ?string $stopReason = 'end_turn';
    public array $items = [['channel' => 'instagram', 'text' => 'Hola {tienda}', 'hashtags' => [], 'cta' => 'Visítanos']];
    public int $calls = 0;
    public string $lastSystem = '';
    public string $lastPrompt = '';

    public function available(): bool
    {
        return $this->available;
    }

    public function structured(string $system, string $prompt, array $schema, int $maxTokens = 4000): ModelResponse
    {
        $this->calls++;
        $this->lastSystem = $system;
        $this->lastPrompt = $prompt;

        if ($this->fail) {
            throw new AiMarketingException('El asistente de IA no respondió. Inténtalo de nuevo en unos minutos.');
        }

        return new ModelResponse(
            data: $this->stopReason === 'refusal' ? null : ['items' => $this->items],
            provider: 'anthropic', model: 'claude-opus-5-5',
            inputTokens: 1000, outputTokens: 500, stopReason: $this->stopReason,
        );
    }
}
