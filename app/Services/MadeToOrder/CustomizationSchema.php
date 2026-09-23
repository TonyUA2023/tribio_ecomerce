<?php

namespace App\Services\MadeToOrder;

use App\Models\Attachment;
use App\Services\Checkout\CheckoutPricingException;
use App\Services\Storefront\ColorScale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * What a made-to-order product asks the buyer (text to embroider, logo, thread color,
 * placement, sizes, date needed) and how each answer affects the unit price.
 *
 * Two sides, one definition:
 *  - normalize(): the merchant's product form → a strict, bounded schema (stored in
 *    products.customization_schema). Anything unexpected is rejected, never stored.
 *  - evaluate(): a buyer's answers for one cart line → a frozen snapshot plus price
 *    extras, computed server-side. The browser's price is never trusted.
 *
 * Extras are expressed in the store's base currency (PEN), like products.price.
 */
final class CustomizationSchema
{
    public const TYPES = [
        'text' => 'Texto corto',
        'textarea' => 'Texto largo',
        'choice' => 'Opciones',
        'file' => 'Archivo (logo o diseño)',
        'sizes' => 'Tallas y cantidades',
        'date' => 'Fecha en que lo necesita',
    ];

    public const MAX_FIELDS = 10;
    public const MAX_OPTIONS = 20;
    public const MAX_SIZES = 12;
    public const MAX_PRICE = 99999;

    /**
     * @param mixed $raw decoded JSON from the product form
     * @throws ValidationException
     */
    public static function normalize(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (!is_array($raw) || !array_is_list($raw)) {
            self::fail('La personalización no tiene un formato válido.');
        }
        if (count($raw) > self::MAX_FIELDS) {
            self::fail('Puedes pedir como máximo ' . self::MAX_FIELDS . ' datos al comprador.');
        }

        $fields = [];
        $keys = [];
        $counts = ['sizes' => 0, 'date' => 0];
        foreach ($raw as $index => $field) {
            $position = $index + 1;
            $type = is_array($field) ? ($field['type'] ?? null) : null;
            if (!is_string($type) || !isset(self::TYPES[$type])) {
                self::fail("El dato #{$position} tiene un tipo no válido.");
            }
            $label = trim(preg_replace('/\s+/u', ' ', (string) ($field['label'] ?? '')));
            if ($label === '' || mb_strlen($label) > 60) {
                self::fail("El dato #{$position} necesita un nombre de hasta 60 caracteres.");
            }

            $key = Str::limit(Str::slug($label, '_'), 40, '') ?: "dato_{$position}";
            $base = $key;
            for ($n = 2; in_array($key, $keys, true); $n++) {
                $key = "{$base}_{$n}";
            }
            $keys[] = $key;

            $normalized = ['key' => $key, 'type' => $type, 'label' => $label, 'required' => filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN)];

            switch ($type) {
                case 'text':
                case 'textarea':
                    [$default, $ceiling] = $type === 'text' ? [40, 200] : [300, 500];
                    $normalized['max'] = max(1, min($ceiling, (int) ($field['max'] ?? $default) ?: $default));
                    $normalized['price'] = self::price($field['price'] ?? 0);
                    break;
                case 'file':
                    $normalized['price'] = self::price($field['price'] ?? 0);
                    break;
                case 'choice':
                    $options = [];
                    foreach (array_values(is_array($field['options'] ?? null) ? $field['options'] : []) as $option) {
                        $optionLabel = trim((string) ($option['label'] ?? ''));
                        if ($optionLabel === '') {
                            continue;
                        }
                        if (mb_strlen($optionLabel) > 40) {
                            self::fail("Las opciones de «{$label}» deben tener hasta 40 caracteres.");
                        }
                        if (in_array($optionLabel, array_column($options, 'label'), true)) {
                            self::fail("«{$label}» tiene la opción «{$optionLabel}» repetida.");
                        }
                        $options[] = [
                            'label' => $optionLabel,
                            'price' => self::price($option['price'] ?? 0),
                            'color' => ColorScale::normalize(is_string($option['color'] ?? null) ? $option['color'] : null),
                        ];
                    }
                    if (count($options) === 0 || count($options) > self::MAX_OPTIONS) {
                        self::fail("«{$label}» necesita entre 1 y " . self::MAX_OPTIONS . ' opciones.');
                    }
                    $normalized['options'] = $options;
                    break;
                case 'sizes':
                    $sizes = array_values(array_unique(array_filter(array_map(
                        fn ($size) => trim((string) $size),
                        is_array($field['sizes'] ?? null) ? $field['sizes'] : explode(',', (string) ($field['sizes'] ?? ''))
                    ), fn ($size) => $size !== '')));
                    if (count($sizes) === 0 || count($sizes) > self::MAX_SIZES || max(array_map('mb_strlen', $sizes)) > 10) {
                        self::fail("«{$label}» necesita entre 1 y " . self::MAX_SIZES . ' tallas de hasta 10 caracteres.');
                    }
                    $normalized['sizes'] = $sizes;
                    $counts['sizes']++;
                    break;
                case 'date':
                    $counts['date']++;
                    break;
            }
            $fields[] = $normalized;
        }

        if ($counts['sizes'] > 1 || $counts['date'] > 1) {
            self::fail('Solo puede haber una tabla de tallas y una fecha por producto.');
        }

        return $fields;
    }

    /**
     * Validates one cart line's answers.
     *
     * @throws CheckoutPricingException with a buyer-facing message
     */
    public static function evaluate(array $schema, mixed $answers, int $quantity, int $leadTimeDays, int $storeId, string $productName): EvaluatedCustomization
    {
        $answers = is_array($answers) ? $answers : [];
        $rows = [];
        $extra = 0.0;
        $tokens = [];
        $requiredBy = null;
        $reject = fn (string $message) => throw new CheckoutPricingException("{$productName}: {$message}");

        foreach ($schema as $field) {
            $label = $field['label'];
            $value = $answers[$field['key']] ?? null;

            switch ($field['type']) {
                case 'text':
                case 'textarea':
                    $text = is_scalar($value) ? trim((string) $value) : '';
                    if ($field['type'] === 'text') {
                        $text = preg_replace('/\s+/u', ' ', $text);
                    }
                    if ($text === '') {
                        $field['required'] && $reject("completa «{$label}».");
                        break;
                    }
                    mb_strlen($text) > $field['max'] && $reject("«{$label}» admite hasta {$field['max']} caracteres.");
                    $rows[] = self::row($field, $text, $field['price']);
                    $extra += $field['price'];
                    break;

                case 'choice':
                    $chosen = is_scalar($value) ? trim((string) $value) : '';
                    if ($chosen === '') {
                        $field['required'] && $reject("elige una opción en «{$label}».");
                        break;
                    }
                    $option = collect($field['options'])->firstWhere('label', $chosen);
                    $option === null && $reject("la opción elegida en «{$label}» ya no está disponible.");
                    $rows[] = self::row($field, $option['label'], $option['price']) + ['color' => $option['color']];
                    $extra += $option['price'];
                    break;

                case 'file':
                    $token = is_string($value) ? trim($value) : '';
                    if ($token === '') {
                        $field['required'] && $reject("sube tu archivo en «{$label}».");
                        break;
                    }
                    $attachment = Attachment::where('token', $token)->where('store_id', $storeId)->whereNull('attachable_type')->first();
                    $attachment === null && $reject("el archivo de «{$label}» ya no está disponible; vuelve a subirlo.");
                    $rows[] = self::row($field, $attachment->original_name, $field['price']) + ['attachment_token' => $token];
                    $tokens[] = $token;
                    $extra += $field['price'];
                    break;

                case 'sizes':
                    $grid = [];
                    foreach ($field['sizes'] as $size) {
                        $units = is_array($value) ? (int) ($value[$size] ?? 0) : 0;
                        ($units < 0 || $units > 9999) && $reject("revisa las cantidades de «{$label}».");
                        if ($units > 0) {
                            $grid[$size] = $units;
                        }
                    }
                    if ($grid === []) {
                        $field['required'] && $reject("indica cuántas unidades quieres por talla en «{$label}».");
                        break;
                    }
                    $quantity = array_sum($grid);
                    $display = implode(', ', array_map(fn ($size, $units) => "{$size} × {$units}", array_keys($grid), $grid));
                    $rows[] = self::row($field, $display, 0) + ['sizes' => $grid];
                    break;

                case 'date':
                    $raw = is_string($value) ? trim($value) : '';
                    if ($raw === '') {
                        $field['required'] && $reject("indica para cuándo lo necesitas.");
                        break;
                    }
                    try {
                        $date = Carbon::createFromFormat('!Y-m-d', $raw);
                    } catch (\Throwable) {
                        $date = null;
                    }
                    ($date === null || $date->format('Y-m-d') !== $raw) && $reject("la fecha de «{$label}» no es válida.");
                    $earliest = today()->addDays(max(0, $leadTimeDays));
                    $date->lt($earliest) && $reject('la fecha más próxima posible es el ' . $earliest->format('d/m/Y') . '.');
                    $rows[] = self::row($field, $date->format('d/m/Y'), 0) + ['date' => $raw];
                    $requiredBy = $raw;
                    break;
            }
        }

        return new EvaluatedCustomization($rows, round($extra, 2), max(1, $quantity), $tokens, $requiredBy);
    }

    private static function row(array $field, string $value, float $price): array
    {
        return ['key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'], 'value' => $value, 'price' => $price];
    }

    private static function price(mixed $value): float
    {
        return round(max(0, min(self::MAX_PRICE, (float) (is_numeric($value) ? $value : 0))), 2);
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['customization_schema' => $message]);
    }
}
