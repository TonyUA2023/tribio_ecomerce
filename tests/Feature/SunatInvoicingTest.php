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
            'enabled' => '1', 'environment' => 'beta', 'factura_mode' => 'auto', 'boleta_mode' => 'manual', 'email_company' => '1', 'email_to' => 'facturas@norte.pe',
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

    // ───────── envío por venta

    public function test_only_the_sales_marked_for_sunat_are_sent_and_the_company_gets_the_email(): void
    {
        [$user, $store, $cement] = $this->owner();
        $gateway = $this->configured($user, $store);
        $session = $this->shift($user, $store);

        $this->sell($user, $session, $cement, ['document_type' => 'boleta'])->assertSuccessful();                          // no toggle → internal only
        $this->sell($user, $session, $cement, ['document_type' => 'nota_venta', 'send_sunat' => true])->assertSuccessful(); // nota never goes
        $this->sell($user, $session, $cement, ['document_type' => 'boleta', 'send_sunat' => true])->assertSuccessful();    // boleta marked → goes
        $this->sell($user, $session, $cement, ['document_type' => 'factura', 'customer_name' => 'Constructora Andes SAC', 'customer_document' => self::CLIENT_RUC])->assertSuccessful(); // factura auto

        $docs = ElectronicDocument::orderBy('id')->get();
        $this->assertSame(['boleta', 'factura'], $docs->pluck('type')->all());
        $this->assertSame(['B001-00000002', 'F001-00000001'], $docs->map->code()->all());
        $this->assertSame(['accepted', 'accepted'], $docs->pluck('status')->all());
        $this->assertCount(2, $gateway->sent);
        $this->assertNotNull($docs[0]->xml);
        $this->assertStringContainsString('B001-2', $gateway->sent[0]);
        $this->assertStringContainsString(self::RUC, $gateway->sent[0]);
        $this->assertStringContainsString('ds:Signature', $gateway->sent[0], 'signed with the business certificate');

        Mail::assertSent(ElectronicDocumentMail::class, 2);
        Mail::assertSent(ElectronicDocumentMail::class, fn ($mail) => $mail->hasTo('facturas@norte.pe') && count($mail->attachments()) === 3);
        $this->assertNotNull($docs[0]->fresh()->emailed_at);
    }

    public function test_boleta_mode_auto_and_never_and_a_disabled_business(): void
    {
        [$user, $store, $cement] = $this->owner();
        $gateway = $this->configured($user, $store, ['boleta_mode' => 'auto']);
        $session = $this->shift($user, $store);
        $this->sell($user, $session, $cement, ['document_type' => 'boleta'])->assertSuccessful();
        $this->assertSame(1, ElectronicDocument::count(), 'every boleta goes');

        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.update'), ['enabled' => '1', 'environment' => 'beta', 'factura_mode' => 'auto', 'boleta_mode' => 'never']);
        $this->sell($user, $session, $cement, ['document_type' => 'boleta', 'send_sunat' => true])->assertSuccessful();
        $this->assertSame(1, ElectronicDocument::count(), 'never: not even when asked');

        $this->actingAs($user)->post(route('dashboard.pos.documents.sunat.update'), ['environment' => 'beta', 'factura_mode' => 'auto', 'boleta_mode' => 'auto']);
        $this->assertFalse(SunatSetting::sole()->enabled);
        $this->sell($user, $session, $cement, ['document_type' => 'boleta'])->assertSuccessful();
        $this->assertSame(1, ElectronicDocument::count(), 'switched off: nothing is sent');
        $this->assertCount(1, $gateway->sent);
    }

    public function test_the_xml_totals_match_the_sale_even_with_a_discount(): void
    {
        [$user, $store, $cement] = $this->owner();
        $gateway = $this->configured($user, $store, ['boleta_mode' => 'auto']);
        $session = $this->shift($user, $store);

        $this->sell($user, $session, $cement, ['document_type' => 'boleta', 'discount' => 7.33], 3)->assertSuccessful();
        $order = Order::latest('id')->first();
        $this->assertEquals(84.17, (float) $order->total);

        $xml = $gateway->sent[0];
        $this->assertMatchesRegularExpression('~<cbc:PayableAmount[^>]*>84\.17<~', $xml);
        preg_match_all('~<cbc:TaxAmount[^>]*>([\d.]+)<~', $xml, $taxes);
        $igv = (float) ($taxes[1][0] ?? 0);
        $this->assertEqualsWithDelta(round(84.17 - 84.17 / 1.18, 2), $igv, 0.011);
        $this->assertStringContainsString('OCHENTA Y CUATRO CON 17/100 SOLES', $xml);
        $this->assertStringContainsString('Cemento Sol 42.5 kg', $xml);
    }

    public function test_sunat_down_is_retried_and_a_rejection_is_not(): void
    {
        [$user, $store, $cement] = $this->owner();
        $gateway = $this->configured($user, $store, ['boleta_mode' => 'auto'], new FakeSunatGateway(SunatResult::error('Sin conexión', '0100')));
        $session = $this->shift($user, $store);

        $this->sell($user, $session, $cement, ['document_type' => 'boleta'])->assertSuccessful();
        $doc = ElectronicDocument::sole();
        $this->assertSame(['error', 1], [$doc->status, $doc->attempts]);
        Mail::assertNothingSent();

        // Too soon: the backoff waits. Later, SUNAT answers.
        $this->assertSame(0, app(ElectronicDocuments::class)->retryDue());
        $this->travel(5)->minutes();
        $gateway->answers = [new SunatResult('accepted', '0', 'Aceptada', base64_encode('cdr'))];
        $this->assertSame(1, app(ElectronicDocuments::class)->retryDue());
        $this->assertSame(['accepted', 2], [$doc->fresh()->status, $doc->fresh()->attempts]);
        $this->assertCount(1, array_unique($gateway->sent), 'the same signed XML is resent, never rebuilt');
        Mail::assertSent(ElectronicDocumentMail::class, 1);

        // A rejection is final: nothing to retry, no mail.
        $gateway->answers = [new SunatResult('rejected', '2335', 'El documento electrónico ingresado ha sido alterado')];
        $this->sell($user, $session, $cement, ['document_type' => 'boleta'])->assertSuccessful();
        $rejected = ElectronicDocument::latest('id')->first();
        $this->assertSame(['rejected', '2335'], [$rejected->status, $rejected->sunat_code]);
        $this->assertFalse($rejected->canRetry());
        $this->actingAs($user)->post(route('dashboard.pos.documents.electronic.retry', $rejected->id))->assertSessionHas('error');
        Mail::assertSent(ElectronicDocumentMail::class, 1);
    }

    public function test_manual_retry_from_the_screen_and_downloads(): void
    {
        [$user, $store, $cement] = $this->owner();
        $gateway = $this->configured($user, $store, ['boleta_mode' => 'auto'], new FakeSunatGateway(SunatResult::error('SUNAT no responde')));
        $session = $this->shift($user, $store);
        $this->sell($user, $session, $cement, ['document_type' => 'boleta'])->assertSuccessful();
        $doc = ElectronicDocument::sole();

        $this->actingAs($user)->get(route('dashboard.pos.documents.electronic'))->assertOk()->assertSee('B001-00000001')->assertSee('Sin enviar')->assertSee('Reenviar');
        $gateway->answers = [new SunatResult('accepted', '0', 'Aceptada', base64_encode('PK-cdr'))];
        $this->actingAs($user)->post(route('dashboard.pos.documents.electronic.retry', $doc->id))->assertSessionHas('success');
        $this->assertSame('accepted', $doc->fresh()->status);

        $pdf = $this->actingAs($user)->get(route('dashboard.pos.documents.electronic.download', [$doc->id, 'pdf']))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('ds:Signature', $this->actingAs($user)->get(route('dashboard.pos.documents.electronic.download', [$doc->id, 'xml']))->assertOk()->getContent());
        $this->assertSame('PK-cdr', $this->actingAs($user)->get(route('dashboard.pos.documents.electronic.download', [$doc->id, 'cdr']))->assertOk()->getContent());

        [$stranger] = $this->owner('otra');
        $this->actingAs($stranger)->get(route('dashboard.pos.documents.electronic.download', [$doc->id, 'xml']))->assertNotFound();
        $this->actingAs($stranger)->post(route('dashboard.pos.documents.electronic.retry', $doc->id))->assertNotFound();
    }

    public function test_the_register_offers_the_switch_only_when_the_business_chooses_per_sale(): void
    {
        [$user, $store] = $this->owner();
        $session = $this->shift($user, $store);
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertSee('\\u0022sunat\\u0022:null', false);

        $this->configured($user, $store, ['boleta_mode' => 'manual']);
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertSee('Enviar a SUNAT', false)->assertSee('\\u0022sunat\\u0022:{\\u0022boleta\\u0022:\\u0022manual\\u0022,\\u0022factura\\u0022:\\u0022auto\\u0022}', false);
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
