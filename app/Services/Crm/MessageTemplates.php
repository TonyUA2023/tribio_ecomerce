<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * WhatsApp templates (Fase 2d): Tribio's own (BUILTIN, editable per store) plus the store's.
 *
 * Placeholders are filled from the database, never typed: {nombre} (first name),
 * {nombre_completo}, {tienda}, {producto}, {pedido}, {saldo}, {link_tienda}. A placeholder
 * without a value (no product, no pending balance) is left out of the message.
 * Promotional templates may only be sent to contacts who accepted offers (Ley 29733).
 */
class MessageTemplates
{
    public const PLACEHOLDERS = [
        'nombre'          => 'Primer nombre del cliente',
        'nombre_completo' => 'Nombre completo',
        'tienda'          => 'Nombre de tu tienda',
        'producto'        => 'Producto (recompras)',
        'pedido'          => 'Último pedido (#NV-123)',
        'saldo'           => 'Saldo pendiente (S/ 120.00)',
        'link_tienda'     => 'Enlace de tu tienda',
    ];

    public const BUILTIN = [
        'reorder' => ['name' => 'Recompra', 'promo' => false,
            'body' => 'Hola {nombre} 👋 ¿Qué tal te va con {producto}? Por mis cuentas ya se te debe estar acabando. ¿Te separo otro?'],
        'thanks' => ['name' => 'Gracias por tu compra', 'promo' => false,
            'body' => '¡Hola {nombre}! Gracias por tu compra en {tienda} 🙌 Cualquier duda sobre tu pedido {pedido}, escríbeme por aquí.'],
        'payment' => ['name' => 'Cobranza amable', 'promo' => false,
            'body' => 'Hola {nombre}, te escribo de {tienda} por tu pedido {pedido}: queda un saldo de {saldo}. ¿Te paso los datos para pagar?'],
        'follow_up' => ['name' => 'Seguimiento', 'promo' => false,
            'body' => 'Hola {nombre}, ¿cómo vas? Te escribo de {tienda} para saber si necesitas algo 😊'],
        'birthday' => ['name' => 'Cumpleaños', 'promo' => false,
            'body' => '¡Feliz cumpleaños, {nombre}! 🎉 Te desea todo el equipo de {tienda}.'],
        'reactivation' => ['name' => 'Te extrañamos', 'promo' => true,
            'body' => 'Hola {nombre}, hace tiempo no sabemos de ti 😊 Mira lo nuevo en {tienda}: {link_tienda}'],
        'news' => ['name' => 'Novedades', 'promo' => true,
            'body' => 'Hola {nombre}, tenemos novedades en {tienda} 👉 {link_tienda}'],
    ];

    /** Which template a follow-up uses for its WhatsApp button. */
    public const FOR_TASK_KIND = ['reorder' => 'reorder', 'payment' => 'payment', 'follow_up' => 'follow_up', 'other' => 'follow_up'];

    public function __construct(private CrmSchema $schema)
    {
    }

    /**
     * Every template of the store: Tribio's (with the store's edits) first, then its own.
     *
     * @return Collection<int, array{key: string, name: string, body: string, promo: bool, builtin: bool, customized: bool}>
     */
    public function all(Store $store): Collection
    {
        $rows = $this->schema->templatesReady()
            ? MessageTemplate::where('store_id', $store->id)->orderBy('id')->get()
            : collect();
        $edits = $rows->whereNotNull('key')->keyBy('key');

        $builtin = collect(self::BUILTIN)->map(fn ($tpl, $key) => [
            'key'        => $key,
            'name'       => $tpl['name'],
            'body'       => $edits[$key]->body ?? $tpl['body'],
            'promo'      => $tpl['promo'],
            'builtin'    => true,
            'customized' => isset($edits[$key]),
        ])->values();

        $own = $rows->whereNull('key')->map(fn (MessageTemplate $row) => [
            'key'        => 'propia-' . $row->id,
            'name'       => $row->name,
            'body'       => $row->body,
            'promo'      => $row->is_promo,
            'builtin'    => false,
            'customized' => true,
        ])->values();

        return $builtin->concat($own)->values();
    }

    public function find(Store $store, string $key): ?array
    {
        return $this->all($store)->firstWhere('key', $key);
    }

    /** The message for this contact, placeholders filled from the database. */
    public function render(string $body, Contact $contact, ?Product $product = null, ?Order $order = null): string
    {
        $values = $this->values($contact, $product, $order);
        $text = preg_replace_callback('/\{([a-z_]+)\}/u', fn ($m) => $values[$m[1]] ?? $m[0], $body);

        // Tidy what an empty placeholder leaves behind ("pedido :", double spaces).
        return trim(preg_replace(['/[ \t]{2,}/', '/ +([,.:;!?])/u'], [' ', '$1'], $text));
    }

    /**
     * Each placeholder's value for this contact ('' when there is none). Also fills the
     * variables of the Meta-approved templates created from these ones (Fase 3b).
     *
     * @return array<string, string>
     */
    public function values(Contact $contact, ?Product $product = null, ?Order $order = null): array
    {
        $store = $contact->store;
        $order ??= $contact->orders()->latest('id')->first();
        $balanceOrder = $order && (float) $order->balance_due > 0 && $this->owes($order->status, $order->payment_status)
            ? $order
            : $this->owing($contact)->latest('id')->first();

        return [
            'nombre'          => strtok(trim($contact->name), ' ') ?: '',
            'nombre_completo' => trim($contact->name),
            'tienda'          => $store?->name ?? '',
            'producto'        => $product?->name ?? '',
            'pedido'          => $order ? '#' . $order->order_number : '',
            'saldo'           => $balanceOrder ? 'S/ ' . number_format((float) $balanceOrder->balance_due, 2) : '',
            'link_tienda'     => $store ? $this->storeUrl($store) : '',
        ];
    }

    /** Whether the contact owes something: templates using {saldo} need it. */
    public function hasBalance(Contact $contact): bool
    {
        return $this->owing($contact)->exists();
    }

    /**
     * Orders that still owe money. An order marked paid owes nothing even if its payments
     * were never written in the ledger (older orders, status changed by hand).
     */
    private function owing(Contact $contact)
    {
        return $contact->orders()->where('balance_due', '>', 0)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereNotIn('payment_status', ['paid', 'refunded', 'failed']);
    }

    private function owes(string $status, ?string $paymentStatus): bool
    {
        return !in_array($status, ['cancelled', 'refunded'], true) && !in_array($paymentStatus, ['paid', 'refunded', 'failed'], true);
    }

    /** wa.me link with the message; null when the contact has no phone. */
    public function whatsappUrl(Contact $contact, string $text): ?string
    {
        return $contact->whatsapp_url ? $contact->whatsapp_url . '?text=' . rawurlencode($text) : null;
    }

    public function saveBuiltin(Store $store, string $key, string $body): void
    {
        abort_unless(isset(self::BUILTIN[$key]), 404);
        MessageTemplate::updateOrCreate(
            ['store_id' => $store->id, 'key' => $key],
            ['name' => self::BUILTIN[$key]['name'], 'body' => $body, 'is_promo' => self::BUILTIN[$key]['promo']],
        );
    }

    public function resetBuiltin(Store $store, string $key): void
    {
        MessageTemplate::where('store_id', $store->id)->where('key', $key)->delete();
    }

    public function createOwn(Store $store, string $name, string $body, bool $promo): MessageTemplate
    {
        if (MessageTemplate::where('store_id', $store->id)->whereNull('key')->count() >= 30) {
            throw ValidationException::withMessages(['name' => 'Puedes tener hasta 30 plantillas propias.']);
        }

        return MessageTemplate::create(['store_id' => $store->id, 'key' => null, 'name' => $name, 'body' => $body, 'is_promo' => $promo]);
    }

    /** The store's own template behind a "propia-{id}" key. */
    public function own(Store $store, string $key): MessageTemplate
    {
        abort_unless(preg_match('/^propia-(\d+)$/', $key, $m), 404);

        return MessageTemplate::where('store_id', $store->id)->whereNull('key')->findOrFail((int) $m[1]);
    }

    public function storeUrl(Store $store): string
    {
        return $store->custom_domain && str_contains($store->custom_domain, '.')
            ? rtrim($store->url, '/')
            : route('store.show', $store->slug);
    }
}
