<?php

namespace App\Services\Pricing;

use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * "Por mayor" pricing of one product: unit-price tiers by the TOTAL quantity of that product
 * in the cart (every size/color line and made-to-order size grid counts together), plus a
 * minimum order quantity.
 *
 * Tiers are stored in the store's base currency (PEN) as absolute unit prices, but applied as
 * a ratio of the product's base price: a variant priced differently (e.g. XXL +S/5) or a visitor
 * paying in USD gets the same proportional reduction, and customization extras are never
 * discounted. Only CheckoutPricing charges from here; the cart mirrors it for display.
 */
final class WholesalePricing
{
    public const MAX_TIERS = 5;
    public const MAX_QUANTITY = 100000;

    private static ?bool $ready = null;

    /** The migration (products.price_tiers, min_quantity) has run. Checked once per request. */
    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasColumns('products', ['price_tiers', 'min_quantity']);
    }

    public static function forgetReady(): void
    {
        self::$ready = null;
    }

    /** @return list<array{min_qty: int, price: float}> ascending by quantity */
    public static function tiers(Product $product): array
    {
        $raw = $product->getAttributes()['price_tiers'] ?? null;
        $tiers = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        if (!is_array($tiers)) {
            return [];
        }

        return collect($tiers)
            ->filter(fn ($t) => is_array($t) && (int) ($t['min_qty'] ?? 0) >= 2 && (float) ($t['price'] ?? 0) > 0)
            ->map(fn ($t) => ['min_qty' => (int) $t['min_qty'], 'price' => round((float) $t['price'], 2)])
            ->sortBy('min_qty')
            ->values()
            ->all();
    }

    public static function minQuantity(Product $product): int
    {
        return max(1, (int) ($product->getAttributes()['min_quantity'] ?? 1));
    }

    /** The tier reached with $quantity units, or null (regular price). */
    public static function tierFor(array $tiers, int $quantity): ?array
    {
        $reached = null;
        foreach ($tiers as $tier) {
            if ($quantity >= $tier['min_qty']) {
                $reached = $tier;
            }
        }

        return $reached;
    }

    /** Multiplier (≤ 1) for the product's base unit price at $quantity total units. */
    public static function factor(Product $product, int $quantity): float
    {
        $base = (float) $product->price;
        $tier = self::tierFor(self::tiers($product), $quantity);
        if (!$tier || $base <= 0) {
            return 1.0;
        }

        return min(1.0, $tier['price'] / $base);
    }

    /**
     * Validates the product form's tiers against the product's price.
     *
     * @return list<array{min_qty: int, price: float}>|null null = no tiers
     * @throws ValidationException (key "price_tiers")
     */
    public static function normalize(mixed $raw, float $basePrice): ?array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }
        if (!is_array($raw) || !array_is_list($raw)) {
            self::fail('Los precios por mayor no tienen un formato válido.');
        }

        $tiers = [];
        foreach ($raw as $row) {
            $qty = is_array($row) ? trim((string) ($row['min_qty'] ?? '')) : '';
            $price = is_array($row) ? trim((string) ($row['price'] ?? '')) : '';
            if ($qty === '' && $price === '') {
                continue; // fila vacía del formulario
            }
            if (!ctype_digit($qty) || (int) $qty < 2 || (int) $qty > self::MAX_QUANTITY) {
                self::fail('Cada precio por mayor necesita una cantidad desde 2 unidades.');
            }
            if (!is_numeric($price) || (float) $price <= 0) {
                self::fail("Escribe el precio por unidad desde {$qty} unidades.");
            }
            $tiers[] = ['min_qty' => (int) $qty, 'price' => round((float) $price, 2)];
        }
        if ($tiers === []) {
            return null;
        }
        if (count($tiers) > self::MAX_TIERS) {
            self::fail('Puedes definir hasta ' . self::MAX_TIERS . ' precios por mayor.');
        }

        usort($tiers, fn ($a, $b) => $a['min_qty'] <=> $b['min_qty']);
        $previousPrice = $basePrice;
        $previousQty = 1;
        foreach ($tiers as $tier) {
            if ($tier['min_qty'] === $previousQty) {
                self::fail("Hay dos precios por mayor para {$tier['min_qty']} unidades.");
            }
            if ($basePrice > 0 && $tier['price'] >= $previousPrice) {
                self::fail("El precio desde {$tier['min_qty']} unidades debe ser menor que el anterior (S/ " . number_format($previousPrice, 2) . ').');
            }
            $previousPrice = $tier['price'];
            $previousQty = $tier['min_qty'];
        }

        return $tiers;
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['price_tiers' => $message]);
    }
}
