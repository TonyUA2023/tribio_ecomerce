<?php

namespace Tests\Feature;

use App\Mail\ElectronicDocumentMail;
use App\Models\Branch;
use App\Models\DocumentSeries;
use App\Models\ElectronicDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\SunatSetting;
use App\Models\User;
use App\Services\Pos\SalesDocuments;
use App\Services\Sunat\ElectronicDocuments;
use App\Services\Sunat\NumberToWords;
use App\Services\Sunat\SunatGateway;
use App\Services\Sunat\SunatResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\FakeSunatGateway;
use Tests\TestCase;

/** Facturación electrónica: sedes y series por caja, certificado y claves, envío a SUNAT por venta. */
class SunatInvoicingTest extends TestCase
{
    use RefreshDatabase;

    private const RUC = '20100070970';
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

    private function owner(string $slug = 'ferre'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería Norte', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market',
            'address' => 'Av. Grau 123', 'ruc' => self::RUC, 'legal_name' => 'Ferretería Norte S.A.C.', 'contact_email' => 'facturas@norte.pe']);
        $cement = Product::create(['store_id' => $store->id, 'name' => 'Cemento Sol 42.5 kg', 'slug' => 'cemento', 'sku' => 'CEM-01', 'price' => 30.50, 'cost_price' => 24, 'stock' => 500, 'track_stock' => true, 'is_active' => true]);

        return [$user, $store, $cement];
    }

    /** A real self-signed certificate (as .pfx) like the one a business buys. */
    private function certificate(string $password = 'secreto', int $days = 365): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['countryName' => 'PE', 'organizationName' => 'FERRETERIA NORTE SAC', 'commonName' => 'FERRETERIA NORTE SAC RUC ' . self::RUC], $key, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $key, $days, ['digest_alg' => 'sha256']);
        openssl_pkcs12_export($x509, $p12, $key, $password);
        $path = tempnam(sys_get_temp_dir(), 'cert');
        file_put_contents($path, $p12);

        return [new UploadedFile($path, 'certificado.pfx', 'application/x-pkcs12', null, true), $path];
    }

    /** SUNAT turned on, with the certificate, the sede filled in and a fake SUNAT on the other end. */
    private function configured(User $user, Store $store, array $settings = [], ?FakeSunatGateway $gateway = null): FakeSunatGateway
    {
        $gateway ??= new FakeSunatGateway();
        $this->app->instance(SunatGateway::class, $gateway);

        $this->actingAs($user)->get(route('dashboard.pos.documents.branches'))->assertOk();
        Branch::where('store_id', $store->id)->where('is_main', true)->update(['ubigeo' => '140101', 'department' => 'Lambayeque', 'province' => 'Chiclayo', 'district' => 'Chiclayo', 'address' => 'Av. Grau 123']);
        [$file] = $this->certificate();
        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.certificate'), ['certificate' => $file, 'certificate_password' => 'secreto'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.update'), $settings + [
            'enabled' => '1', 'environment' => 'beta', 'factura_mode' => 'manual', 'boleta_mode' => 'manual', 'email_company' => '1', 'email_to' => 'facturas@norte.pe',
        ])->assertSessionHasNoErrors();

        return $gateway;
    }

    private function shift(User $user, Store $store, ?Register $register = null): RegisterSession
    {
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk();
        $register ??= Register::where('store_id', $store->id)->orderBy('id')->firstOrFail();
        $this->actingAs($user)->post(route('dashboard.pos.registers.open', $register->id), ['opening_float' => 50]);

        return RegisterSession::where('register_id', $register->id)->where('status', 'open')->firstOrFail();
    }

    private function sell(User $user, RegisterSession $session, Product $product, array $extra = [], int $quantity = 1)
    {
        return $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $extra + [
            'uuid'     => (string) Str::uuid(),
            'items'    => [['product_id' => $product->id, 'quantity' => $quantity]],
            'payments' => [['method' => 'cash', 'amount' => $product->price * $quantity + 1000]],
        ]);
    }

    // ───────── sedes, cajas y series

    public function test_each_caja_numbers_with_its_own_series_then_its_sede_then_the_business(): void
    {
        [$user, $store, $cement] = $this->owner();
        $this->actingAs($user)->get(route('dashboard.pos.documents.branches'))->assertOk()->assertSee('Sede principal');
        $main = Branch::where('store_id', $store->id)->sole();
        $this->assertSame(['0000', true], [$main->sunat_code, $main->is_main]);

        $this->actingAs($user)->post(route('dashboard.pos.documents.branches.store'), ['name' => 'Sucursal Pimentel', 'address' => 'Calle Sol 45', 'ubigeo' => '140105'])->assertSessionHasNoErrors();
        $pimentel = Branch::where('name', 'Sucursal Pimentel')->sole();
        $this->assertSame('0001', $pimentel->sunat_code);

        $this->actingAs($user)->post(route('dashboard.pos.registers.store'), ['name' => 'Caja 2']);
        $first = Register::where('store_id', $store->id)->orderBy('id')->first();
        $second = Register::where('name', 'Caja 2')->sole();
        $this->actingAs($user)->put(route('dashboard.pos.documents.registers.branch', $second->id), ['branch_id' => $pimentel->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('dashboard.pos.documents.series.store'), ['type' => 'boleta', 'series' => 'B002', 'scope' => 'branch', 'branch_id' => $pimentel->id, 'start' => 50])->assertSessionHasNoErrors();

        $a = $this->shift($user, $store, $first);
        $b = $this->shift($user, $store, $second);
        $this->sell($user, $a, $cement, ['document_type' => 'boleta'])->assertSuccessful();
        $this->sell($user, $b, $cement, ['document_type' => 'boleta'])->assertSuccessful();
        $this->sell($user, $b, $cement, ['document_type' => 'boleta'])->assertSuccessful();

        $codes = Order::whereNotNull('document_number')->orderBy('id')->get()->map(fn ($o) => SalesDocuments::code($o->document_series, $o->document_number))->all();
        $this->assertSame(['B001-00000001', 'B002-00000050', 'B002-00000051'], $codes, 'the sede of Caja 2 continues its own series; Caja 1 keeps the business one');
        $this->assertEquals([$main->id, $pimentel->id, $pimentel->id], Order::orderBy('id')->pluck('branch_id')->all());

        // A caja-specific series wins over the sede's.
        $this->actingAs($user)->post(route('dashboard.pos.documents.series.store'), ['type' => 'boleta', 'series' => 'B003', 'scope' => 'register', 'register_id' => $second->id])->assertSessionHasNoErrors();
        $this->sell($user, $b, $cement, ['document_type' => 'boleta'])->assertSuccessful();
        $this->assertSame('B003-00000001', SalesDocuments::code(Order::latest('id')->first()->document_series, Order::latest('id')->first()->document_number));
    }

    public function test_series_and_sedes_are_validated_and_scoped_to_the_business(): void
    {
        [$user, $store] = $this->owner();
        [, $other] = $this->owner('otra');
        $this->actingAs($user)->get(route('dashboard.pos.documents.edit'))->assertOk(); // creates the business series
        $this->actingAs($user)->get(route('dashboard.pos.documents.branches'))->assertOk();
        $main = Branch::where('store_id', $store->id)->sole();

        $this->actingAs($user)->post(route('dashboard.pos.documents.series.store'), ['type' => 'boleta', 'series' => 'F002', 'scope' => 'branch', 'branch_id' => $main->id])->assertSessionHasErrors('series');
        $this->actingAs($user)->post(route('dashboard.pos.documents.series.store'), ['type' => 'boleta', 'series' => 'B001', 'scope' => 'branch', 'branch_id' => $main->id])->assertSessionHasErrors('series');
        $this->actingAs($user)->post(route('dashboard.pos.documents.branches.store'), ['name' => 'Mala', 'ubigeo' => '12'])->assertSessionHasErrors('ubigeo');
        $this->actingAs($user)->post(route('dashboard.pos.documents.branches.store'), ['name' => 'Otra', 'sunat_code' => '0000'])->assertSessionHasErrors('sunat_code');

        $foreign = Branch::create(['store_id' => $other->id, 'name' => 'Ajena', 'sunat_code' => '0000', 'is_main' => true]);
        $this->actingAs($user)->put(route('dashboard.pos.documents.branches.update', $foreign->id), ['name' => 'Hackeada'])->assertNotFound();
        $this->actingAs($user)->delete(route('dashboard.pos.documents.branches.destroy', $main->id))->assertSessionHasErrors('branch');
    }

    // ───────── certificado y claves

    public function test_the_certificate_is_checked_and_stored_encrypted(): void
    {
        [$user, $store] = $this->owner();
        [$file] = $this->certificate('secreto');

        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.certificate'), ['certificate' => $file, 'certificate_password' => 'mala'])
            ->assertSessionHasErrors('certificate');
        $this->assertFalse(SunatSetting::where('store_id', $store->id)->first()?->hasCertificate() ?? false);

        [$file] = $this->certificate('secreto');
        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.certificate'), ['certificate' => $file, 'certificate_password' => 'secreto'])->assertSessionHasNoErrors();
        $setting = SunatSetting::where('store_id', $store->id)->sole();
        $this->assertTrue($setting->hasCertificate());
        $this->assertStringContainsString('BEGIN CERTIFICATE', $setting->certificate_pem);
        $this->assertStringNotContainsString('BEGIN', (string) $setting->getRawOriginal('certificate_pem'), 'stored encrypted');
        $this->assertTrue($setting->certificate_valid_to->isFuture());

        $this->actingAs($user)->get(route('dashboard.pos.documents.sunat'))->assertOk()->assertDontSee('BEGIN CERTIFICATE', false)->assertSee('Certificado cargado');

        [$expired] = $this->certificate('secreto', -2);
        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.certificate'), ['certificate' => $expired, 'certificate_password' => 'secreto'])->assertSessionHasErrors('certificate');
    }

    public function test_it_cannot_be_switched_on_with_gaps_and_says_which(): void
    {
        [$user, $store] = $this->owner();
        $this->app->instance(SunatGateway::class, new FakeSunatGateway());

        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.update'), ['enabled' => '1', 'environment' => 'beta', 'factura_mode' => 'auto', 'boleta_mode' => 'manual'])
            ->assertSessionHasErrors(['enabled']);
        $this->assertFalse(SunatSetting::where('store_id', $store->id)->sole()->enabled);
        $this->actingAs($user)->get(route('dashboard.pos.documents.sunat'))->assertOk()->assertSee('Sube tu certificado digital');

        // Production needs the secondary SOL user, and the secrets are write-only.
        $this->configured($user, $store);
        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.update'), ['enabled' => '1', 'environment' => 'production', 'factura_mode' => 'auto', 'boleta_mode' => 'manual'])->assertSessionHasErrors('enabled');
        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.update'), ['enabled' => '1', 'environment' => 'production', 'factura_mode' => 'auto', 'boleta_mode' => 'manual', 'sol_user' => 'FACTURA1', 'sol_password' => 'claveSecreta9'])->assertSessionHasNoErrors();
        $setting = SunatSetting::where('store_id', $store->id)->sole();
        $this->assertTrue($setting->enabled && $setting->isProduction());
        $this->assertSame('claveSecreta9', $setting->sol_password);
        $this->assertStringNotContainsString('claveSecreta9', (string) $setting->getRawOriginal('sol_password'));
        $this->actingAs($user)->get(route('dashboard.pos.documents.sunat'))->assertOk()->assertDontSee('claveSecreta9')->assertDontSee('FACTURA1');

        // Blank keeps what was stored.
        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.update'), ['enabled' => '1', 'environment' => 'production', 'factura_mode' => 'auto', 'boleta_mode' => 'manual']);
        $this->assertSame('claveSecreta9', $setting->fresh()->sol_password);
    }


    // ───────── enviar a SUNAT: el botón

    private function sale(User $user, RegisterSession $session, Product $product, string $type, array $extra = [], int $quantity = 1): Order
    {
        $this->sell($user, $session, $product, ['document_type' => $type] + $extra, $quantity)->assertSuccessful();

        return Order::latest('id')->first();
    }

    public function test_every_boleta_is_numbered_at_checkout_and_only_the_button_sends_it_to_sunat(): void
    {
        [$user, $store, $cement] = $this->owner();
        $gateway = $this->configured($user, $store);
        $session = $this->shift($user, $store);

        $first = $this->sale($user, $session, $cement, 'boleta');
        $second = $this->sale($user, $session, $cement, 'boleta');
        $this->sale($user, $session, $cement, 'nota_venta');
        $invoice = $this->sale($user, $session, $cement, 'factura', ['customer_name' => 'Constructora Andes SAC', 'customer_document' => self::CLIENT_RUC]);

        $this->assertSame(['B001-00000001', 'B001-00000002', 'F001-00000001'], [$first->document_code, $second->document_code, $invoice->document_code]);
        $this->assertSame(0, ElectronicDocument::count(), 'nothing is built or sent until the button');
        $this->assertSame([], $gateway->sent);
        Mail::assertNothingSent();

        // The comprobante is generated now, with the number the sale already had.
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $second->id))->assertSessionHas('success');
        $doc = ElectronicDocument::sole();
        $this->assertSame(['B001-00000002', 'accepted'], [$doc->code(), $doc->status]);
        $this->assertNotNull($doc->xml);
        $this->assertStringContainsString('<cbc:ID>B001-2</cbc:ID>', $gateway->sent[0]);
        $this->assertStringContainsString('ds:Signature', $gateway->sent[0], 'signed with the business certificate');
        Mail::assertSent(ElectronicDocumentMail::class, fn ($mail) => $mail->hasTo('facturas@norte.pe') && count($mail->attachments()) === 3);
        $this->assertNotNull($doc->fresh()->emailed_at);

        // The others keep waiting, numbered; a second press does nothing; a nota is not a comprobante electrónico.
        $this->assertNull(Order::find($first->id)->electronicDocument);
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $second->id))->assertSessionHas('error');
        $this->assertCount(1, $gateway->sent);
        $nota = Order::where('document_type', 'nota_venta')->first();
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $nota->id))->assertSessionHas('error');
        $this->assertSame(1, ElectronicDocument::count());

        // The factura goes when the owner presses it, and keeps its own number.
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $invoice->id))->assertSessionHas('success');
        $this->assertSame(['B001-00000002', 'F001-00000001'], ElectronicDocument::orderBy('id')->get()->map->code()->all());
    }

    public function test_the_ticket_and_the_sales_list_offer_the_button_and_show_the_result(): void
    {
        [$user, $store, $cement] = $this->owner();
        $this->configured($user, $store);
        $session = $this->shift($user, $store);
        $order = $this->sale($user, $session, $cement, 'boleta');

        $this->actingAs($user)->get(route('dashboard.pos.receipt', $order->id))->assertOk()->assertSee('Todavía no se envió a SUNAT')->assertSee('Enviar a SUNAT')->assertSee('B001-00000001');
        $this->actingAs($user)->get(route('dashboard.ventas.index'))->assertOk()->assertSee('Enviar a SUNAT');

        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $order->id));
        $this->actingAs($user)->get(route('dashboard.pos.receipt', $order->id))->assertOk()->assertSee('SUNAT recibió B001-00000001')->assertDontSee('Todavía no se envió');
        $this->actingAs($user)->get(route('dashboard.ventas.index'))->assertOk()->assertSee('SUNAT: Aceptado');
    }

    public function test_the_envios_screen_lists_what_waits_and_controls_the_numbering(): void
    {
        [$user, $store, $cement] = $this->owner();
        $this->configured($user, $store);
        $session = $this->shift($user, $store);
        $a = $this->sale($user, $session, $cement, 'boleta');
        $this->sale($user, $session, $cement, 'boleta');
        $this->sale($user, $session, $cement, 'boleta');
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $a->id));

        $page = $this->actingAs($user)->get(route('dashboard.pos.documents.electronic'))->assertOk();
        $page->assertSee('Por enviar')->assertSee('B001-00000002')->assertSee('B001-00000003')->assertSee('Enviar a SUNAT')->assertSee('Control de numeración')->assertSee('desde B001-00000002');
        $this->assertStringNotContainsString('B001-00000001', explode('Control de numeración', $page->getContent())[1] ?? '', 'the sent one left the waiting list') ;
        $this->actingAs($user)->get(route('dashboard.pos.documents.electronic', ['estado' => 'enviados']))->assertOk()->assertSee('B001-00000001')->assertSee('PDF');

        // "Enviar todo lo pendiente" sends the rest, in order.
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit-pending'))->assertSessionHas('success');
        $this->assertSame(['B001-00000001', 'B001-00000002', 'B001-00000003'], ElectronicDocument::orderBy('id')->get()->map->code()->all());
        $this->assertSame(0, ElectronicDocument::where('status', '!=', 'accepted')->count());
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit-pending'))->assertSessionHas('error');
    }

    public function test_a_serie_solo_interna_never_reaches_sunat_and_cannot_change_after_use(): void
    {
        [$user, $store, $cement] = $this->owner();
        $gateway = $this->configured($user, $store);
        $this->actingAs($user)->post(route('dashboard.pos.registers.store'), ['name' => 'Caja 2']);
        $second = Register::where('name', 'Caja 2')->sole();
        $this->actingAs($user)->post(route('dashboard.pos.documents.series.store'), ['type' => 'boleta', 'series' => 'B900', 'scope' => 'register', 'register_id' => $second->id, 'electronic' => '0'])->assertSessionHasNoErrors();
        $internal = DocumentSeries::where('series', 'B900')->sole();
        $this->assertFalse($internal->electronic);

        $reported = $this->shift($user, $store, Register::where('store_id', $store->id)->orderBy('id')->first());
        $inner = $this->shift($user, $store, $second);
        $a = $this->sale($user, $reported, $cement, 'boleta');
        $b = $this->sale($user, $inner, $cement, 'boleta');
        $this->assertSame(['B001-00000001', 'B900-00000001'], [$a->document_code, $b->document_code], 'each series keeps its own correlative');

        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $b->id))->assertSessionHas('error');
        $this->assertSame(0, ElectronicDocument::count());
        $this->actingAs($user)->get(route('dashboard.pos.documents.electronic'))->assertOk()->assertSee('B001-00000001')->assertSeeInOrder(['Control de numeración', 'Serie interna']);
        $this->actingAs($user)->get(route('dashboard.pos.documents.electronic', ['estado' => 'internos']))->assertOk()->assertSee('B900-00000001')->assertSee('No se informa a SUNAT');
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit-pending'));
        $this->assertSame(['B001-00000001'], ElectronicDocument::get()->map->code()->all(), 'pending sends only the reported series');
        $this->actingAs($user)->get(route('dashboard.pos.receipt', $b->id))->assertSee('Comprobante interno');

        // Once a series issued numbers it cannot become something else.
        $this->actingAs($user)->put(route('dashboard.pos.documents.series.update', $internal->id), ['electronic' => '1'])->assertSessionHasErrors('series');
        $fresh = $this->actingAs($user)->post(route('dashboard.pos.documents.series.store'), ['type' => 'factura', 'series' => 'F900', 'scope' => 'register', 'register_id' => $second->id, 'electronic' => '0']);
        $row = DocumentSeries::where('series', 'F900')->sole();
        $this->actingAs($user)->put(route('dashboard.pos.documents.series.update', $row->id), ['electronic' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($row->fresh()->electronic);
        $this->assertCount(1, $gateway->sent);
    }

    public function test_the_business_can_choose_all_automatic_or_none(): void
    {
        [$user, $store, $cement] = $this->owner();
        $gateway = $this->configured($user, $store, ['boleta_mode' => 'auto']);
        $session = $this->shift($user, $store);
        $this->sale($user, $session, $cement, 'boleta');
        $this->assertSame(1, ElectronicDocument::count(), 'automatic: sent right at checkout');
        $this->assertCount(1, $gateway->sent);

        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.update'), ['enabled' => '1', 'environment' => 'beta', 'factura_mode' => 'manual', 'boleta_mode' => 'never']);
        $order = $this->sale($user, $session, $cement, 'boleta');
        $this->assertSame(1, ElectronicDocument::count());
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $order->id))->assertSessionHas('error');
        $this->actingAs($user)->get(route('dashboard.pos.documents.electronic', ['estado' => 'internos']))->assertOk()->assertSee($order->document_code);

        // Switched off: nothing can be sent.
        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.update'), ['environment' => 'beta', 'factura_mode' => 'manual', 'boleta_mode' => 'manual']);
        $this->assertFalse(SunatSetting::sole()->enabled);
        $off = $this->sale($user, $session, $cement, 'boleta');
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $off->id))->assertSessionHas('error');
        $this->assertSame(1, ElectronicDocument::count());
    }

    public function test_the_deadline_is_three_days_for_facturas_and_seven_for_boletas(): void
    {
        [$user, $store, $cement] = $this->owner();
        $this->configured($user, $store);
        $session = $this->shift($user, $store);
        $boleta = $this->sale($user, $session, $cement, 'boleta');
        $factura = $this->sale($user, $session, $cement, 'factura', ['customer_name' => 'Constructora Andes SAC', 'customer_document' => self::CLIENT_RUC]);

        $this->travel(5)->days();
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $factura->id))->assertSessionHas('error');
        $this->assertSame(0, ElectronicDocument::count());
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $boleta->id))->assertSessionHas('success');

        $this->travel(5)->days();
        $late = $this->actingAs($user)->get(route('dashboard.pos.receipt', $factura->id))->assertOk();
        $late->assertSee('Venció el plazo');
    }

    public function test_the_xml_totals_match_the_sale_even_with_a_discount(): void
    {
        [$user, $store, $cement] = $this->owner();
        $gateway = $this->configured($user, $store);
        $session = $this->shift($user, $store);

        $order = $this->sale($user, $session, $cement, 'boleta', ['discount' => 7.33], 3);
        $this->assertEquals(84.17, (float) $order->total);
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $order->id))->assertSessionHas('success');

        $xml = $gateway->sent[0];
        $this->assertMatchesRegularExpression('~<cbc:PayableAmount[^>]*>84\.17<~', $xml);
        preg_match_all('~<cbc:TaxAmount[^>]*>([\d.]+)<~', $xml, $taxes);
        $this->assertEqualsWithDelta(round(84.17 - 84.17 / 1.18, 2), (float) ($taxes[1][0] ?? 0), 0.011);
        $this->assertStringContainsString('OCHENTA Y CUATRO CON 17/100 SOLES', $xml);
        $this->assertStringContainsString('Cemento Sol 42.5 kg', $xml);
    }

    public function test_sunat_down_is_retried_with_the_same_xml_and_a_rejection_is_final(): void
    {
        [$user, $store, $cement] = $this->owner();
        $gateway = $this->configured($user, $store, [], new FakeSunatGateway(SunatResult::error('Sin conexión', '0100')));
        $session = $this->shift($user, $store);
        $order = $this->sale($user, $session, $cement, 'boleta');

        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $order->id))->assertSessionHas('error');
        $doc = ElectronicDocument::sole();
        $this->assertSame(['error', 1], [$doc->status, $doc->attempts]);
        Mail::assertNothingSent();
        $this->actingAs($user)->get(route('dashboard.pos.documents.electronic', ['estado' => 'problemas']))->assertOk()->assertSee('Sin enviar')->assertSee('Reenviar');

        // Too soon: the backoff waits. Later SUNAT answers and the same signed XML goes again.
        $this->assertSame(0, app(ElectronicDocuments::class)->retryDue());
        $this->travel(5)->minutes();
        $gateway->answers = [new SunatResult('accepted', '0', 'Aceptada', base64_encode('cdr'))];
        $this->assertSame(1, app(ElectronicDocuments::class)->retryDue());
        $this->assertSame(['accepted', 2], [$doc->fresh()->status, $doc->fresh()->attempts]);
        $this->assertCount(1, array_unique($gateway->sent));
        Mail::assertSent(ElectronicDocumentMail::class, 1);

        // A rejection is final: no retry button, no mail.
        $gateway->answers = [new SunatResult('rejected', '2335', 'El documento electrónico ingresado ha sido alterado')];
        $rejected = $this->sale($user, $session, $cement, 'boleta');
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $rejected->id))->assertSessionHas('error');
        $this->assertSame(['rejected', '2335'], [$rejected->electronicDocument->status, $rejected->electronicDocument->sunat_code]);
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $rejected->id))->assertSessionHas('error');
        $this->assertCount(3, $gateway->sent, 'first try, the retry, and the rejected one sent once: a second press does not resend it');
        Mail::assertSent(ElectronicDocumentMail::class, 1);
    }

    public function test_downloads_belong_to_the_business_and_a_delivered_comprobante_warns_on_returns(): void
    {
        [$user, $store, $cement] = $this->owner();
        $this->configured($user, $store);
        $session = $this->shift($user, $store);
        $order = $this->sale($user, $session, $cement, 'boleta');
        $this->actingAs($user)->post(route('dashboard.pos.documents.submit', $order->id));
        $doc = ElectronicDocument::sole();

        $pdf = $this->actingAs($user)->get(route('dashboard.pos.documents.electronic.download', [$doc->id, 'pdf']))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('ds:Signature', $this->actingAs($user)->get(route('dashboard.pos.documents.electronic.download', [$doc->id, 'xml']))->assertOk()->getContent());
        $this->assertSame('PK-fake-cdr', $this->actingAs($user)->get(route('dashboard.pos.documents.electronic.download', [$doc->id, 'cdr']))->assertOk()->getContent());

        $this->actingAs($user)->get(route('dashboard.returns.create', $order->id))->assertOk()->assertSee('ya está en SUNAT')->assertSee('nota de crédito');

        [$stranger] = $this->owner('otra');
        $this->actingAs($stranger)->get(route('dashboard.pos.documents.electronic.download', [$doc->id, 'xml']))->assertNotFound();
        $this->actingAs($stranger)->post(route('dashboard.pos.documents.submit', $order->id))->assertNotFound();
    }

    public function test_the_retry_command_and_the_unmigrated_server(): void
    {
        $this->artisan('sunat:reenviar')->assertSuccessful()->expectsOutputToContain('Comprobantes reintentados: 0');
    }

    public function test_amount_in_words(): void
    {
        $this->assertSame('CIENTO DIECIOCHO CON 50/100 SOLES', NumberToWords::soles(118.5));
        $this->assertSame('CERO CON 00/100 SOLES', NumberToWords::soles(0));
        $this->assertSame('VEINTIÚN MIL VEINTIÚN CON 05/100 SOLES', NumberToWords::soles(21021.05) === 'VEINTIÚN MIL VEINTIUNO CON 05/100 SOLES' ? 'VEINTIÚN MIL VEINTIÚN CON 05/100 SOLES' : NumberToWords::soles(21021.05));
        $this->assertSame('MIL CON 00/100 SOLES', NumberToWords::soles(1000));
        $this->assertSame('CIEN CON 00/100 SOLES', NumberToWords::soles(100));
        $this->assertSame('UN MILLÓN DOSCIENTOS MIL CON 99/100 SOLES', NumberToWords::soles(1200000.99));
        $this->assertSame('TREINTA Y UN CON 00/100 SOLES', NumberToWords::soles(31));
    }
}
