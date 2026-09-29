<?php

namespace Tests\Feature;

use App\Mail\StoreContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Dashboard "Mensajes": the storefront contact form, the virtual Libro de Reclamaciones and
 * newsletter sign-ups finally reach the owner (inbox + e-mail).
 */
class MessagesInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function store(string $slug = 'apachi', array $attributes = []): Store
    {
        return Store::create($attributes + [
            'user_id' => User::factory()->create(['role' => 'store_owner', 'email' => "{$slug}@example.test"])->id,
            'name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active', 'template_name' => 'textil-pro',
        ]);
    }

    public function test_a_storefront_message_is_stored_unread_and_the_owner_is_emailed(): void
    {
        Mail::fake();
        $store = $this->store('apachi', ['contact_email' => 'ventas@apachi.test']);

        $this->post(route('store.contact.submit', $store->slug), [
            'name' => 'Rosa Quispe', 'email' => 'rosa@example.test', 'phone' => '987654321',
            'subject' => 'Cotización', 'message' => 'Quiero 50 polos con el logo de mi colegio.', 'is_read' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $message = ContactMessage::sole();
        $this->assertFalse($message->is_read, 'A posted is_read must not skip the inbox');
        Mail::assertQueued(StoreContactMessageReceived::class, fn ($mail) => $mail->hasTo('ventas@apachi.test') && $mail->hasReplyTo('rosa@example.test'));
        Mail::assertQueued(StoreContactMessageReceived::class, fn ($mail) => str_contains($mail->render(), 'Quiero 50 polos con el logo de mi colegio.'));

        $this->post(route('store.contact.submit', $store->slug), ['name' => 'X', 'email' => 'x@example.test', 'message' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('message');
    }

    public function test_the_owner_reads_messages_by_kind_and_they_become_read(): void
    {
        $store = $this->store();
        $complaint = $store->contactMessages()->create(['name' => 'Luis', 'email' => 'luis@example.test', 'subject' => 'Libro de Reclamaciones — Reclamo', 'message' => 'Mi pedido llegó incompleto.']);
        $store->contactMessages()->create(['name' => 'Ana', 'email' => 'ana@example.test', 'subject' => 'Cotización', 'message' => 'Precio por 100 polos']);
        $store->contactMessages()->create(['name' => 'Suscriptor', 'email' => 'news@example.test', 'subject' => 'Suscripción a novedades', 'message' => 'Quiero recibir novedades']);

        $this->actingAs($store->user)->get(route('dashboard.mensajes.index'))->assertOk()
            ->assertSee('Luis')->assertSee('Ana')->assertSee('Suscriptor')
            ->assertSee('Libro de Reclamaciones')
            ->assertSee('aria-label="3 sin leer"', false);

        $this->get(route('dashboard.mensajes.index', ['filtro' => 'libro']))->assertOk()
            ->assertSee('Luis')->assertDontSee('Precio por 100 polos');

        $this->get(route('dashboard.mensajes.show', $complaint->id))->assertOk()
            ->assertSee('Mi pedido llegó incompleto.')
            ->assertSee('15 días hábiles')
            ->assertSee('mailto:luis@example.test', false);
        $this->assertTrue($complaint->fresh()->is_read);

        $this->patch(route('dashboard.mensajes.unread', $complaint->id))->assertRedirect(route('dashboard.mensajes.index'));
        $this->assertFalse($complaint->fresh()->is_read);
    }

    public function test_an_owner_cannot_open_another_stores_messages(): void
    {
        $mine = $this->store('apachi');
        $other = $this->store('otra');
        $foreign = $other->contactMessages()->create(['name' => 'Privado', 'email' => 'p@example.test', 'message' => 'Solo para la otra tienda']);

        $this->actingAs($mine->user)->get(route('dashboard.mensajes.show', $foreign->id))->assertNotFound();
        $this->get(route('dashboard.mensajes.index'))->assertOk()->assertDontSee('Solo para la otra tienda');
        $this->patch(route('dashboard.mensajes.unread', $foreign->id))->assertNotFound();
    }
}
