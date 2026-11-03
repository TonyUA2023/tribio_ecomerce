<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\Deal;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Crm\DealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** ERP Fase 2e: oportunidades that move through stages, close as won/lost and leave a trace in the timeline. */
class DealsTest extends TestCase
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
        Mail::fake();
    }

    private function owner(string $slug = 'nutri'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri Vida', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);

        return [$user, $store];
    }

    private function order(Store $store, string $number, float $total, string $phone = '987111222'): Order
    {
        return Order::create(['store_id' => $store->id, 'order_number' => $number, 'customer_name' => 'Rosa Quispe', 'customer_phone' => $phone,
            'customer_country' => 'PE', 'subtotal' => $total, 'total' => $total, 'currency' => 'PEN', 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'yape']);
    }

    private function contact(Store $store): Contact
    {
        $this->order($store, 'NV-1', 80);

        return Contact::sole();
    }

    private function create(User $user, Contact $contact, array $data = []): Deal
    {
        $this->actingAs($user)->post(route('dashboard.clientes.deals.store'), $data + ['contact_id' => $contact->id, 'title' => '50 packs para el gimnasio'])
            ->assertSessionHasNoErrors()->assertRedirect();

        return Deal::latest('id')->first();
    }

    public function test_a_deal_is_created_and_logged_in_the_timeline(): void
    {
        [$user, $store] = $this->owner();
        $rosa = $this->contact($store);

        $deal = $this->create($user, $rosa, ['amount' => 1500, 'expected_close_at' => now()->addWeek()->toDateString()]);

        $this->assertSame('nuevo', $deal->stage);
        $this->assertSame($store->id, $deal->store_id);
        $this->assertSame($user->id, $deal->user_id);
        $this->assertSame('1500.00', $deal->amount);
        $this->assertSame('Nueva oportunidad: 50 packs para el gimnasio (S/ 1,500.00)', ContactEvent::where('type', 'deal')->sole()->body);
    }

    public function test_creating_needs_a_title_and_a_customer_of_the_same_store(): void
    {
        [$user, $store] = $this->owner();
        [, $other] = $this->owner('otra');
        $foreign = $this->contact($other);
        $this->order($store, 'NV-9', 20, '955444333');
        $rosa = Contact::where('store_id', $store->id)->sole();

        $this->actingAs($user)->post(route('dashboard.clientes.deals.store'), ['contact_id' => $rosa->id, 'title' => ''])->assertSessionHasErrors('title');
        $this->actingAs($user)->post(route('dashboard.clientes.deals.store'), ['contact_id' => $foreign->id, 'title' => 'Robo'])
            ->assertSessionHasErrors(['contact_id' => 'Ese cliente no es de tu tienda.']);
        $this->actingAs($user)->post(route('dashboard.clientes.deals.store'), ['title' => 'Sin cliente'])->assertSessionHasErrors('contact_id');
        $this->assertSame(0, Deal::count());
    }

    public function test_moving_between_open_stages_is_logged(): void
    {
        [$user, $store] = $this->owner();
        $deal = $this->create($user, $this->contact($store));

        $this->actingAs($user)->post(route('dashboard.clientes.deals.move', $deal->id), ['stage' => 'cotizado'])
            ->assertSessionHas('success', '«50 packs para el gimnasio» pasó a Cotizado.');
        $this->assertSame('cotizado', $deal->fresh()->stage);
        $this->assertTrue(ContactEvent::where('body', '«50 packs para el gimnasio»: Nuevo → Cotizado')->exists());

        $this->actingAs($user)->post(route('dashboard.clientes.deals.move', $deal->id), ['stage' => 'ganada'])->assertSessionHasErrors('stage');
        $this->assertSame('cotizado', $deal->fresh()->stage, 'Closing goes through win/lose, not the move select');
    }

    public function test_winning_with_an_order_takes_its_total_when_no_amount_was_estimated(): void
    {
        [$user, $store] = $this->owner();
        $rosa = $this->contact($store);
        $big = $this->order($store, 'NV-2', 640);
        $deal = $this->create($user, $rosa);

        $this->actingAs($user)->post(route('dashboard.clientes.deals.win', $deal->id), ['order_id' => $big->id])->assertSessionHasNoErrors();

        $deal->refresh();
        $this->assertSame('ganada', $deal->stage);
        $this->assertSame($big->id, $deal->order_id);
        $this->assertSame('640.00', $deal->amount);
        $this->assertNotNull($deal->closed_at);
        $this->assertTrue(ContactEvent::where('body', '🏆 Ganada: «50 packs para el gimnasio» (S/ 640.00) · pedido #NV-2')->exists());

        $this->actingAs($user)->post(route('dashboard.clientes.deals.move', $deal->id), ['stage' => 'nuevo'])
            ->assertSessionHasErrors(['stage' => 'Esta oportunidad ya se cerró. Vuelve a abrirla para moverla.']);
    }

    public function test_an_order_of_another_customer_cannot_close_the_deal(): void
    {
        [$user, $store] = $this->owner();
        $deal = $this->create($user, $this->contact($store), ['amount' => 300]);
        $other = $this->order($store, 'NV-3', 90, '955444333');

        $this->actingAs($user)->post(route('dashboard.clientes.deals.win', $deal->id), ['order_id' => $other->id])
            ->assertSessionHasErrors(['order_id' => 'Ese pedido no es de este cliente.']);
        $this->assertTrue($deal->fresh()->isOpen());
    }

    public function test_losing_needs_a_reason_and_reopening_clears_the_close(): void
    {
        [$user, $store] = $this->owner();
        $deal = $this->create($user, $this->contact($store));

        $this->actingAs($user)->post(route('dashboard.clientes.deals.lose', $deal->id), [])
            ->assertSessionHasErrors(['lost_reason' => 'Elige por qué se perdió: sirve para mejorar.']);
        $this->actingAs($user)->post(route('dashboard.clientes.deals.lose', $deal->id), ['lost_reason' => 'precio', 'lost_note' => 'Encontró más barato'])
            ->assertSessionHasNoErrors();

        $deal->refresh();
        $this->assertSame(['perdida', 'precio', 'Encontró más barato'], [$deal->stage, $deal->lost_reason, $deal->lost_note]);
        $this->assertTrue(ContactEvent::where('body', 'Perdida: «50 packs para el gimnasio» — Le pareció caro (Encontró más barato)')->exists());

        $this->actingAs($user)->post(route('dashboard.clientes.deals.reopen', $deal->id))->assertSessionHasNoErrors();
        $deal->refresh();
        $this->assertSame(['negociando', null, null, null], [$deal->stage, $deal->closed_at, $deal->lost_reason, $deal->lost_note]);
    }

    public function test_the_summary_adds_open_value_and_the_win_rate(): void
    {
        [$user, $store] = $this->owner();
        $rosa = $this->contact($store);
        $service = app(DealService::class);

        $service->create($rosa, ['title' => 'A', 'amount' => 100], $user->id);
        $b = $service->create($rosa, ['title' => 'B', 'amount' => 250], $user->id);
        $service->move($b, 'negociando', $user->id);
        $service->win($service->create($rosa, ['title' => 'C', 'amount' => 400], $user->id), null, $user->id);
        $service->lose($service->create($rosa, ['title' => 'D', 'amount' => 50], $user->id), 'no_respondio', null, $user->id);
        $old = $service->create($rosa, ['title' => 'E', 'amount' => 999], $user->id);
        $service->win($old, null, $user->id);
        $old->forceFill(['closed_at' => now()->subDays(120)])->save();

        $summary = $service->summary($store);

        $this->assertSame(350.0, $summary['open_amount']);
        $this->assertSame(['count' => 1, 'amount' => 100.0], $summary['stages']['nuevo']);
        $this->assertSame(['count' => 1, 'amount' => 250.0], $summary['stages']['negociando']);
        $this->assertSame(['count' => 0, 'amount' => 0.0], $summary['stages']['cotizado']);
        $this->assertSame([400.0, 1, 1, 50.0], [$summary['won_amount'], $summary['won'], $summary['lost'], $summary['win_rate']], 'Only the last 90 days count');
    }

    public function test_the_board_and_the_contact_page_show_the_deals(): void
    {
        [$user, $store] = $this->owner();
        $rosa = $this->contact($store);
        $deal = $this->create($user, $rosa, ['amount' => 1200, 'expected_close_at' => now()->subDays(2)->toDateString()]);

        $this->actingAs($user)->get(route('dashboard.clientes.deals.index'))->assertOk()
            ->assertSee('50 packs para el gimnasio')->assertSee('(atrasada)')->assertSee('S/ 1,200.00');
        $this->actingAs($user)->get(route('dashboard.clientes.show', $rosa->id))->assertOk()
            ->assertSee('Oportunidades')->assertSee('50 packs para el gimnasio')->assertSee('#NV-1');
        $this->actingAs($user)->get(route('dashboard.clientes.index'))->assertSee('💼 Oportunidades');

        $this->actingAs($user)->delete(route('dashboard.clientes.deals.destroy', $deal->id))->assertRedirect();
        $this->assertSame(0, Deal::count());
    }

    public function test_deals_of_another_store_are_not_reachable(): void
    {
        [$user] = $this->owner();
        [$intruder, $other] = $this->owner('otra');
        $deal = $this->create($user, $this->contact(Store::where('slug', 'nutri')->sole()));

        foreach (['move' => ['stage' => 'cotizado'], 'win' => [], 'lose' => ['lost_reason' => 'precio'], 'reopen' => []] as $action => $data) {
            $this->actingAs($intruder)->post(route("dashboard.clientes.deals.{$action}", $deal->id), $data)->assertNotFound();
        }
        $this->actingAs($intruder)->delete(route('dashboard.clientes.deals.destroy', $deal->id))->assertNotFound();
        $this->actingAs($intruder)->get(route('dashboard.clientes.deals.index'))->assertOk()->assertDontSee('Rosa Quispe');
        $this->assertSame('nuevo', $deal->fresh()->stage);
    }
}
