<?php

namespace App\Services\AiMarketing;

use App\Models\BrandKit;
use App\Models\MarketingAsset;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Str;

/**
 * Marketing → Contenido: social-media copy written by the AI in the store's voice.
 *
 * The model never writes a price, discount, link or phone. It writes placeholders
 * ({precio}, {descuento}, …) that are replaced here with the product's real data, and
 * any amount that still appears afterwards is flagged for the owner to review.
 */
class CopyGenerator
{
    public const FEATURE = 'copy';

    public const OBJECTIVES = [
        'oferta'      => 'Promocionar una oferta o descuento',
        'destacado'   => 'Destacar un producto',
        'lanzamiento' => 'Presentar un producto nuevo',
        'recompra'    => 'Invitar a volver a comprar',
        'temporada'   => 'Campaña por fecha especial',
    ];

    public const CHANNELS = [
        'instagram'        => 'Instagram',
        'facebook'         => 'Facebook',
        'tiktok'           => 'TikTok',
        'whatsapp_estado'  => 'Estado de WhatsApp',
        'whatsapp_mensaje' => 'Mensaje de WhatsApp',
    ];

    private const CHANNEL_RULES = [
        'instagram'        => 'Instagram: gancho en la primera línea, 3 a 6 líneas cortas, emojis con moderación, 4 a 8 hashtags.',
        'facebook'         => 'Facebook: 2 a 5 líneas, tono conversacional, 0 a 3 hashtags.',
        'tiktok'           => 'TikTok: máximo 150 caracteres, gancho directo, 3 a 5 hashtags.',
        'whatsapp_estado'  => 'Estado de WhatsApp: máximo 120 caracteres, sin hashtags.',
        'whatsapp_mensaje' => 'Mensaje de WhatsApp a un cliente: saludo breve, 2 a 4 líneas, cercano, sin hashtags, sin sonar a spam.',
    ];

    public function __construct(private TextModel $model, private AiCredits $credits)
    {
    }

    public function available(): bool
    {
        return $this->model->available();
    }

    /** @param list<string> $channels */
    public function generate(Store $store, ?Product $product, string $objective, array $channels, ?string $notes, ?int $userId): MarketingAsset
    {
        if (!$this->model->available()) {
            throw new AiMarketingException('Estamos activando el asistente de IA. Apenas esté listo podrás usarlo aquí.');
        }
        $this->credits->ensure($store, self::FEATURE);

        $kit = BrandKit::forStore($store);
        $facts = $this->facts($store, $product);

        $response = $this->model->structured(
            $this->systemPrompt($store, $kit),
            $this->userPrompt($product, $facts, $objective, $channels, $notes),
            $this->schema($channels),
        );

        $items = $response->data['items'] ?? null;
        if ($response->refused() || !is_array($items) || $items === []) {
            $this->credits->record($store, $userId, self::FEATURE, $response, $response->refused() ? 'refused' : 'error', 'empty or refused');
            throw new AiMarketingException('No pudimos escribir este texto. Cambia un poco las indicaciones y vuelve a intentarlo (no se descontaron créditos).');
        }

        $items = collect($items)
            ->filter(fn ($item) => is_array($item) && in_array($item['channel'] ?? null, $channels, true) && filled($item['text'] ?? null))
            ->map(fn (array $item) => $this->finish($item, $facts, $kit))
            ->values()->all();

        $this->credits->record($store, $userId, self::FEATURE, $response);

        return MarketingAsset::create([
            'store_id'   => $store->id,
            'product_id' => $product?->id,
            'user_id'    => $userId,
            'kind'       => MarketingAsset::KIND_COPY,
            'template'   => $objective,
            'title'      => $product?->name ?? self::OBJECTIVES[$objective],
            'content'    => ['objective' => $objective, 'channels' => $channels, 'notes' => $notes, 'items' => $items],
        ]);
    }

    /**
     * Real values for every placeholder the model may use. Only placeholders with a
     * value are offered, so the model never promises a discount the product doesn't have.
     *
     * @return array<string, string>
     */
    public function facts(Store $store, ?Product $product): array
    {
        $facts = ['tienda' => $store->name];

        if ($product) {
            $facts['producto'] = $product->name;
            $facts['precio'] = self::money((float) $product->price);
            if ($product->compare_price && (float) $product->compare_price > (float) $product->price) {
                $facts['precio_antes'] = self::money((float) $product->compare_price);
                $facts['descuento'] = $product->discount_percent . '%';
            }
            $facts['link'] = $store->custom_domain && str_contains($store->custom_domain, '.')
                ? rtrim($store->url, '/') . '/producto/' . $product->slug
                : route('store.product', [$store->slug, $product->slug]);
        } else {
            $facts['link'] = $store->custom_domain && str_contains($store->custom_domain, '.')
                ? rtrim($store->url, '/')
                : route('store.show', $store->slug);
        }

        if ($store->whatsapp_phone) {
            $facts['whatsapp'] = trim($store->whatsapp_phone);
        }

        return $facts;
    }

    public static function money(float $amount): string
    {
        return 'S/ ' . number_format($amount, 2, '.', ',');
    }

    private function systemPrompt(Store $store, BrandKit $kit): string
    {
        $tone = BrandKit::TONES[$kit->tone] ?? BrandKit::TONES['cercano'];
        $banned = collect($kit->banned_words ?? [])->filter()->implode(', ');

        return implode("\n", array_filter([
            "Eres el redactor de redes sociales de \"{$store->name}\", un negocio en Perú.",
            'Escribes en español natural de Perú, claro y fácil de leer en el celular.',
            "Tono de la marca: {$tone}.",
            $kit->slogan ? "Eslogan de la marca: \"{$kit->slogan}\"." : null,
            $kit->audience ? "Público al que le vende: {$kit->audience}." : null,
            '',
            'Reglas obligatorias:',
            '1. Nunca escribas montos, precios, porcentajes, links ni números de teléfono. Usa solo los marcadores entre llaves que te doy, tal cual (por ejemplo {precio}). Si un marcador no está en la lista, ese dato no existe: no lo menciones.',
            '2. No inventes datos: nada de stock, plazos de entrega, regalos, envío gratis ni garantías que no estén en la información.',
            '3. No hagas promesas de salud, curación, pérdida de peso ni resultados garantizados, aunque el producto sea un suplemento.',
            '4. No uses mayúsculas sostenidas ni más de 3 emojis por línea.',
            $banned ? "5. Nunca uses estas palabras o frases: {$banned}." : null,
        ], fn ($line) => $line !== null));
    }

    /** @param array<string, string> $facts */
    private function userPrompt(?Product $product, array $facts, string $objective, array $channels, ?string $notes): string
    {
        $lines = ['Objetivo: ' . self::OBJECTIVES[$objective] . '.'];

        if ($product) {
            $description = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($product->short_description ?: $product->description)))), 700);
            $lines[] = "Producto: {$product->name}.";
            if ($description !== '') {
                $lines[] = "Descripción del dueño: {$description}";
            }
            if ($product->category?->name) {
                $lines[] = "Categoría: {$product->category->name}.";
            }
            if (!empty($product->price_tiers)) {
                $lines[] = 'Tiene precios por mayor (menciona que hay precio por mayor, sin montos).';
            }
        } else {
            $lines[] = 'No es sobre un producto en particular: es sobre la tienda en general.';
        }

        $lines[] = 'Marcadores disponibles: ' . collect(array_keys($facts))->map(fn ($key) => '{' . $key . '}')->implode(', ') . '.';
        if ($notes) {
            $lines[] = 'Indicaciones del dueño: ' . Str::limit(trim($notes), 400);
        }

        $lines[] = '';
        $lines[] = 'Escribe 2 versiones distintas para cada canal:';
        foreach ($channels as $channel) {
            $lines[] = '- ' . self::CHANNEL_RULES[$channel];
        }
        $lines[] = 'En "cta" pon una llamada a la acción corta (máximo 6 palabras). Los hashtags van sin el símbolo # y no se repiten en el texto.';

        return implode("\n", $lines);
    }

    private function schema(array $channels): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'channel'  => ['type' => 'string', 'enum' => array_values($channels)],
                            'text'     => ['type' => 'string'],
                            'hashtags' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'cta'      => ['type' => 'string'],
                        ],
                        'required' => ['channel', 'text', 'hashtags', 'cta'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['items'],
            'additionalProperties' => false,
        ];
    }

    /** Replace placeholders with real data and flag anything the owner should double-check. */
    private function finish(array $item, array $facts, BrandKit $kit): array
    {
        $fill = function (string $text) use ($facts): string {
            $text = preg_replace_callback('/\{([a-z_]+)\}/u', fn ($m) => $facts[$m[1]] ?? '', $text);

            return trim(preg_replace('/[ \t]{2,}/', ' ', $text));
        };

        $text = $fill((string) $item['text']);
        $cta = $fill((string) ($item['cta'] ?? ''));
        $hashtags = collect($item['hashtags'] ?? [])
            ->map(fn ($tag) => preg_replace('/[^\pL\pN_]/u', '', (string) $tag))
            ->filter()->unique()->take(10)->values()->all();

        return [
            'channel'  => $item['channel'],
            'text'     => $text,
            'cta'      => $cta,
            'hashtags' => $hashtags,
            'warnings' => $this->warnings($text . ' ' . $cta, $facts, $kit),
        ];
    }

    /** @return list<string> */
    private function warnings(string $text, array $facts, BrandKit $kit): array
    {
        $warnings = [];
        $allowedMoney = array_filter([$facts['precio'] ?? null, $facts['precio_antes'] ?? null]);

        preg_match_all('/(?:S\/|US\$|\$)\s?\d[\d.,]*/u', $text, $money);
        foreach ($money[0] as $amount) {
            $normalized = self::money((float) str_replace(',', '', preg_replace('/^(?:S\/|US\$|\$)\s?/u', '', $amount)));
            if (!in_array($normalized, $allowedMoney, true)) {
                $warnings[] = "Menciona {$amount}, que no es el precio de tu producto. Revísalo antes de publicar.";
            }
        }

        preg_match_all('/\b(\d{1,3})\s?%/u', $text, $percents);
        foreach ($percents[0] as $i => $percent) {
            if (($percents[1][$i] . '%') !== ($facts['descuento'] ?? null)) {
                $warnings[] = "Menciona {$percent}, que no es el descuento de tu producto. Revísalo antes de publicar.";
            }
        }

        foreach ($kit->banned_words ?? [] as $word) {
            if (filled($word) && Str::contains(Str::lower($text), Str::lower($word))) {
                $warnings[] = "Usa \"{$word}\", que marcaste como palabra prohibida.";
            }
        }

        return array_values(array_unique($warnings));
    }
}
