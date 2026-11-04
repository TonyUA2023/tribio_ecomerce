<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\Order;
use App\Models\Product;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use App\Services\Pos\SalesDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Comprobantes: nota de venta, boleta de venta y factura de la caja. */
class SalesDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private const STORE_RUC = '20100070970';
    private const CLIENT_RUC = '20131312955';

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();
    }

    private function owner(string $slug = 'ferre', ?string $ruc = null): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería Norte', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market',
            'address' => 'Av. Grau 123', 'ruc' => $ruc, 'legal_name' => $ruc ? 'Ferretería Norte S.A.C.' : null]);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Cemento', 'slug' => 'cemento', 'price' => 30, 'stock' => 500, 'track_stock' => true, 'is_active' => true]);
        $pricey = Product::create(['store_id' => $store->id, 'name' => 'Amoladora', 'slug' => 'amoladora', 'price' => 900, 'stock' => 20, 'track_stock' => true, 'is_active' => true]);

        return [$user, $store, $product, $pricey];
    }

    private function shift(User $user, Store $store): RegisterSession
    {
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk();
        $register = Register::where('store_id', $store->id)->sole();
        $this->actingAs($user)->post(route('dashboard.pos.registers.open', $register->id), ['opening_float' => 100]);

        return RegisterSession::where('register_id', $register->id)->sole();
    }

    private function sell(User $user, RegisterSession $session, Product $product, array $extra = [], int $quantity = 1)
    {
        $total = $product->price * $quantity;

        return $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $extra + [
            'uuid'     => (string) Str::uuid(),
            'items'    => [['product_id' => $product->id, 'quantity' => $quantity]],
            'payments' => [['method' => 'cash', 'amount' => $total]],
        ]);
    }

    public function test_ruc_check_digit_is_validated(): void
    {
        $this->assertTrue(SalesDocuments::validRuc(self::STORE_RUC));
        $this->assertTrue(SalesDocuments::validRuc(self::CLIENT_RUC));
        $this->assertFalse(SalesDocuments::validRuc('20100070971'), 'wrong check digit');
        $this->assertFalse(SalesDocuments::validRuc('30100070970'), 'not a SUNAT prefix');
        $this->assertFalse(SalesDocuments::validRuc('2010007097'), '10 digits');
        $this->assertTrue(SalesDocuments::validRuc(SalesDocuments::rucFrom('2012345678')));
        $this->assertSame(['taxable' => 100.0, 'igv' => 18.0], SalesDocuments::breakdown(118.0));
    }

    public function test_a_sale_without_choice_is_a_nota_de_venta_with_its_own_correlative(): void
    {
        [$user, $store, $product] = $this->owner();
        $session = $this->shift($user, $store);

        $first = $this->sell($user, $session, $product)->assertCreated();
        $second = $this->sell($user, $session, $product)->assertCreated();

        $first->assertJsonPath('document_type', 'nota_venta')->assertJsonPath('document_label', 'Nota de venta')->assertJsonPath('document_number', 'NV01-00000001');
        $second->assertJsonPath('document_number', 'NV01-00000002');
        $this->assertSame(2, DocumentSeries::where('store_id', $store->id)->where('type', 'nota_venta')->value('last_number'));
    }

    public function test_boleta_and_factura_need_the_ruc_of_the_business(): void
    {
        [$user, $store, $product] = $this->owner();
        $session = $this->shift($user, $store);

        $this->sell($user, $session, $product, ['document_type' => 'boleta'])
            ->assertStatus(422)->assertJsonValidationErrors('document_type');
        $this->assertSame(0, Order::where('store_id', $store->id)->count(), 'nothing is sold when the comprobante is refused');
    }

    public function test_boleta_numbers_run_apart_from_the_other_documents(): void
    {
        [$user, $store, $product] = $this->owner('ferre', self::STORE_RUC);
        $session = $this->shift($user, $store);

        $this->sell($user, $session, $product)->assertJsonPath('document_number', 'NV01-00000001');
        $this->sell($user, $session, $product, ['document_type' => 'boleta'])->assertCreated()->assertJsonPath('document_label', 'Boleta de venta')->assertJsonPath('document_number', 'B001-00000001');
        $this->sell($user, $session, $product, ['document_type' => 'boleta'])->assertJsonPath('document_number', 'B001-00000002');
        $this->sell($user, $session, $product)->assertJsonPath('document_number', 'NV01-00000002');
    }

    public function test_a_boleta_above_700_soles_needs_the_buyers_dni(): void
    {
        [$user, $store, , $pricey] = $this->owner('ferre', self::STORE_RUC);
        $session = $this->shift($user, $store);

        $this->sell($user, $session, $pricey, ['document_type' => 'boleta'])->assertStatus(422)->assertJsonValidationErrors('document_type');
        $this->sell($user, $session, $pricey, ['document_type' => 'boleta', 'customer_name' => 'Ana Ruiz', 'customer_document' => '45678912'])
            ->assertCreated()->assertJsonPath('document_number', 'B001-00000001');
        // The same amount as a nota de venta needs nobody.
        $this->sell($user, $session, $pricey)->assertCreated();
    }

    public function test_a_factura_needs_a_valid_ruc_and_a_razon_social(): void
    {
        [$user, $store, $product] = $this->owner('ferre', self::STORE_RUC);
        $session = $this->shift($user, $store);

        $this->sell($user, $session, $product, ['document_type' => 'factura'])->assertStatus(422);
        $this->sell($user, $session, $product, ['document_type' => 'factura', 'customer_name' => 'Constructora Andina SAC', 'customer_document' => '20131312956'])->assertStatus(422);
        $this->sell($user, $session, $product, ['document_type' => 'factura', 'customer_document' => self::CLIENT_RUC])->assertStatus(422);
        $this->sell($user, $session, $product, ['document_type' => 'factura', 'customer_name' => '45678912', 'customer_document' => '45678912'])->assertStatus(422);

        $ok = $this->sell($user, $session, $product, ['document_type' => 'factura', 'customer_name' => 'Constructora Andina SAC',
            'customer_document' => self::CLIENT_RUC, 'customer_address' => 'Jr. Lima 450, Chiclayo'], 3)->assertCreated();
        $ok->assertJsonPath('document_label', 'Factura')->assertJsonPath('document_number', 'F001-00000001');

        $order = Order::findOrFail($ok->json('order_id'));
        $this->assertSame(['RUC', self::CLIENT_RUC, 'Jr. Lima 450, Chiclayo'], [$order->customer_document_type, $order->customer_document_number, $order->customer_address]);
    }

    public function test_repeating_a_sale_does_not_burn_another_number(): void
    {
        [$user, $store, $product] = $this->owner('ferre', self::STORE_RUC);
        $session = $this->shift($user, $store);
        $uuid = (string) Str::uuid();

        $this->sell($user, $session, $product, ['uuid' => $uuid, 'document_type' => 'boleta'])->assertCreated();
        $this->sell($user, $session, $product, ['uuid' => $uuid, 'document_type' => 'boleta'])->assertOk()->assertJsonPath('document_number', 'B001-00000001');
        $this->sell($user, $session, $product, ['document_type' => 'boleta'])->assertJsonPath('document_number', 'B001-00000002');
    }

    public function test_every_store_has_its_own_series(): void
    {
        [$userA, $storeA, $productA] = $this->owner('ferre-a', self::STORE_RUC);
        [$userB, $storeB, $productB] = $this->owner('ferre-b', self::STORE_RUC);
        $sessionA = $this->shift($userA, $storeA);
        $sessionB = $this->shift($userB, $storeB);

        $this->sell($userA, $sessionA, $productA, ['document_type' => 'boleta'])->assertJsonPath('document_number', 'B001-00000001');
        $this->sell($userB, $sessionB, $productB, ['document_type' => 'boleta'])->assertJsonPath('document_number', 'B001-00000001');
    }

    public function test_the_ticket_shows_the_document_the_issuer_and_the_igv(): void
    {
        [$user, $store, $product] = $this->owner('ferre', self::STORE_RUC);
        $session = $this->shift($user, $store);

        $boleta = $this->sell($user, $session, $product, ['document_type' => 'boleta'], 4)->assertCreated();   // S/ 120.00
        $this->actingAs($user)->get($boleta->json('receipt_url'))->assertOk()
            ->assertSee('BOLETA DE VENTA')->assertSee('B001-00000001')->assertSee('RUC ' . self::STORE_RUC)->assertSee('Ferretería Norte S.A.C.')
            ->assertSee('Op. gravada')->assertSee('101.69')->assertSee('IGV (18%)')->assertSee('18.31')->assertSee('120.00');

        $factura = $this->sell($user, $session, $product, ['document_type' => 'factura', 'customer_name' => 'Constructora Andina SAC', 'customer_document' => self::CLIENT_RUC])->assertCreated();
        $this->actingAs($user)->get($factura->json('receipt_url'))->assertOk()->assertSee('FACTURA')->assertSee('F001-00000001')->assertSee('Razón social: Constructora Andina SAC', false);

        $nota = $this->sell($user, $session, $product)->assertCreated();
        $this->actingAs($user)->get($nota->json('receipt_url'))->assertOk()->assertSee('NOTA DE VENTA')->assertSee('NV01-00000001')->assertDontSee('IGV (18%)')->assertDontSee('RUC ' . self::STORE_RUC);
    }

    public function test_the_register_offers_the_three_documents_and_the_settings_page_loads(): void
    {
        [$user, $store] = $this->owner();
        $this->shift($user, $store);

        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertSee('Comprobante')->assertSee('Boleta')->assertSee('Factura');
        $this->actingAs($user)->get(route('dashboard.pos.index', ['cajas' => 1]))->assertOk()->assertSee('Comprobantes');
        $this->actingAs($user)->get(route('dashboard.pos.documents.edit'))->assertOk()->assertSee('NV01')->assertSee('B001')->assertSee('F001')->assertSee('no se envían a SUNAT');
    }

    public function test_the_owner_saves_the_ruc_and_changes_a_series(): void
    {
        [$user, $store, $product] = $this->owner('ferre', self::STORE_RUC);
        $session = $this->shift($user, $store);
        $this->sell($user, $session, $product, ['document_type' => 'boleta'])->assertCreated();
        $series = ['nota_venta' => 'NV01', 'boleta' => 'B001', 'factura' => 'F001'];

        $this->actingAs($user)->post(route('dashboard.pos.documents.update'), ['ruc' => '20100070971', 'legal_name' => 'X', 'series' => $series])->assertSessionHasErrors('ruc');
        $this->actingAs($user)->post(route('dashboard.pos.documents.update'), ['ruc' => self::STORE_RUC, 'series' => ['boleta' => 'X001'] + $series])->assertSessionHasErrors('series.boleta');
        $this->actingAs($user)->post(route('dashboard.pos.documents.update'), ['ruc' => self::STORE_RUC, 'series' => ['factura' => 'B001'] + $series])->assertSessionHasErrors('series.factura');

        $this->actingAs($user)->post(route('dashboard.pos.documents.update'), ['ruc' => '20 100 070 970', 'legal_name' => 'Nuevo Nombre SAC', 'series' => ['boleta' => 'b002'] + $series])
            ->assertRedirect(route('dashboard.pos.documents.edit'));

        $this->assertSame([self::STORE_RUC, 'Nuevo Nombre SAC'], [$store->fresh()->ruc, $store->fresh()->legal_name]);
        $this->sell($user, $session, $product, ['document_type' => 'boleta'])->assertJsonPath('document_number', 'B002-00000001');
        $this->sell($user, $session, $product)->assertJsonPath('document_number', 'NV01-00000001');
    }

    public function test_the_owner_edits_the_business_basics_from_the_same_page(): void
    {
        [$user, $store] = $this->owner('ferre', self::STORE_RUC);
        $series = ['nota_venta' => 'NV01', 'boleta' => 'B001', 'factura' => 'F001'];

        $this->actingAs($user)->get(route('dashboard.pos.documents.edit'))->assertOk()->assertSee('Nombre del negocio')->assertSee('Av. Grau 123');
        $this->actingAs($user)->post(route('dashboard.pos.documents.update'), ['ruc' => self::STORE_RUC, 'series' => $series, 'name' => 'Ferretería Sur', 'phone' => '987 654 321', 'address' => 'Jr. Lima 45', 'city' => 'Piura'])
            ->assertRedirect(route('dashboard.pos.documents.edit'));
        $this->assertSame(['Ferretería Sur', '987 654 321', 'Jr. Lima 45', 'Piura'], [$store->fresh()->name, $store->fresh()->phone, $store->fresh()->address, $store->fresh()->city]);

        // Leaving the basics out of the form erases nothing; an empty name is refused.
        $this->actingAs($user)->post(route('dashboard.pos.documents.update'), ['ruc' => self::STORE_RUC, 'series' => $series])->assertRedirect();
        $this->assertSame('Ferretería Sur', $store->fresh()->name);
        $this->actingAs($user)->post(route('dashboard.pos.documents.update'), ['ruc' => self::STORE_RUC, 'series' => $series, 'name' => ''])->assertSessionHasErrors('name');
    }

    public function test_cashiers_in_kiosk_mode_cannot_open_the_settings(): void
    {
        [$user, $store] = $this->owner();
        $this->shift($user, $store);
        $guard = new \ReflectionClassConstant(\App\Http\Middleware\PosKioskGuard::class, 'OWNER_ONLY');

        $this->assertContains('dashboard.pos.documents.*', $guard->getValue());
    }
}
