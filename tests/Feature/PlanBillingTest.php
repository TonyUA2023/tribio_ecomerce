<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Tribio's own plan billing through Mercado Pago Suscripciones. */
class PlanBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mp_billing.access_token' => 'APP_USR-test']);
        Http::preventStrayRequests();
    }

    private function draftStore(array $attributes = []): array
    {
        $owner = User::factory()->create(['role' => 'store_owner', 'email' => 'dueno@example.test']);
        $store = Store::create($attributes + [
            'user_id' => $owner->id, 'name' => 'Nintai', 'slug' => 'nintai', 'status' => 'draft',
            'template_name' => 'soft-market', 'plan' => 'basic',
        ]);

        return [$owner, $store];
    }

    public function test_subscribe_creates_a_monthly_preapproval_and_redirects_to_mercado_pago(): void
    {
        [$owner, $store] = $this->draftStore();
        Http::fake(['api.mercadopago.com/preapproval' => Http::response(['id' => 'pre-1', 'init_point' => 'https://www.mercadopago.com.pe/subscriptions/checkout?preapproval_id=pre-1'])]);

        $this->actingAs($owner)->get(route('plan.pay', $store))->assertOk()->assertSee('Mercado Pago')->assertDontSee('Culqi');
        $this->post(route('plan.subscribe', $store))
            ->assertRedirect('https://www.mercadopago.com.pe/subscriptions/checkout?preapproval_id=pre-1');

        $this->assertSame('pre-1', $store->fresh()->billing_preapproval_id);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.mercadopago.com/preapproval'
            && $r['auto_recurring']['frequency_type'] === 'months'
            && $r['auto_recurring']['transaction_amount'] == config('tribio.plans.basic.price')
            && $r['payer_email'] === 'dueno@example.test'
            && $r['external_reference'] === 'store-' . $store->id
            && $r['back_url'] === route('plan.return', $store));
    }

    public function test_returning_from_mercado_pago_activates_only_an_authorized_subscription(): void
    {
        [$owner, $store] = $this->draftStore(['billing_preapproval_id' => 'pre-1']);
        Http::fake(['api.mercadopago.com/preapproval/pre-1' => Http::sequence()
            ->push(['id' => 'pre-1', 'status' => 'pending'])
            ->push(['id' => 'pre-1', 'status' => 'authorized', 'next_payment_date' => now()->addMonth()->toIso8601String()])]);

        $this->actingAs($owner)->get(route('plan.return', $store))->assertRedirect(route('plan.pay', $store));
        $this->assertSame('draft', $store->fresh()->status);

        $this->get(route('plan.return', $store))->assertRedirect(route('dashboard.index'));
        $store->refresh();
        $this->assertSame('active', $store->status);
        $this->assertTrue($store->plan_expires_at->isFuture());
    }

    public function test_a_query_string_alone_never_activates_a_store(): void
    {
        [$owner, $store] = $this->draftStore(['billing_preapproval_id' => 'pre-1']);
        Http::fake(['api.mercadopago.com/preapproval/pre-1' => Http::response(['id' => 'pre-1', 'status' => 'pending'])]);

        $this->actingAs($owner)->get(route('plan.return', $store) . '?status=authorized&preapproval_id=pre-1')
            ->assertRedirect(route('plan.pay', $store));
        $this->assertSame('draft', $store->fresh()->status);
    }

    public function test_webhook_extends_the_plan_on_a_charge_and_cancels_on_cancellation(): void
    {
        [, $store] = $this->draftStore(['status' => 'active', 'plan_expires_at' => now()->addDay(), 'billing_preapproval_id' => 'pre-1']);
        $next = now()->addMonth()->startOfSecond();
        Http::fake([
            'api.mercadopago.com/authorized_payments/ap-1' => Http::response(['id' => 'ap-1', 'preapproval_id' => 'pre-1', 'status' => 'processed']),
            'api.mercadopago.com/preapproval/pre-1' => Http::sequence()
                ->push(['id' => 'pre-1', 'status' => 'authorized', 'next_payment_date' => $next->toIso8601String()])
                ->push(['id' => 'pre-1', 'status' => 'cancelled']),
        ]);

        $this->postJson(route('plan.webhook'), ['type' => 'subscription_authorized_payment', 'data' => ['id' => 'ap-1']])->assertOk();
        $this->assertTrue($store->fresh()->plan_expires_at->gt(now()->addDays(20)));

        $this->postJson(route('plan.webhook'), ['type' => 'subscription_preapproval', 'data' => ['id' => 'pre-1']])->assertOk();
        $this->assertSame('cancelled', $store->fresh()->status);

        $this->postJson(route('plan.webhook'), ['type' => 'payment', 'data' => ['id' => '9']])->assertOk()->assertJson(['status' => 'ignored']);
    }

    public function test_another_user_cannot_start_or_finish_a_subscription_for_a_store(): void
    {
        [, $store] = $this->draftStore();
        $other = User::factory()->create(['role' => 'store_owner']);

        $this->actingAs($other)->post(route('plan.subscribe', $store))->assertForbidden();
        $this->get(route('plan.return', $store))->assertForbidden();
    }

    public function test_without_credentials_the_page_explains_payments_are_not_active(): void
    {
        config(['services.mp_billing.access_token' => null]);
        [$owner, $store] = $this->draftStore();

        $this->actingAs($owner)->get(route('plan.pay', $store))->assertOk()->assertSee('todavía no están activos');
        $this->post(route('plan.subscribe', $store))->assertRedirect(route('plan.pay', $store));
    }
}
