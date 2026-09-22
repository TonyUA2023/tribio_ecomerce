<?php

namespace App\Services\Storefront;

/**
 * Derives a full tint/shade scale from one brand color, so a store owner picks a single
 * color (or takes it from their logo) and the whole template stays harmonious. The
 * dashboard's live preview ports these exact ratios to JS (resources/js/template-customizer.js)
 * — change both together or the preview will drift from the real storefront.
 */
class ColorScale
{
    public const WHITE = [255, 255, 255];
    public const BLACK = [0, 0, 0];

    /** Validated `#RRGGBB` or null. Anything else is rejected, never echoed into CSS. */
    public static function normalize(?string $hex): ?string
    {
        if (!is_string($hex) || !preg_match('/^#?([0-9a-fA-F]{6})$/', trim($hex), $m)) {
            return null;
        }

        return '#' . strtoupper($m[1]);
    }

    public static function primary(string $hex): array
    {
        $rgb = self::rgb($hex);

        return [
            'primary'      => self::hex($rgb),
            'primary-dark' => self::hex(self::mix($rgb, self::BLACK, .40)),
            'primary-deep' => self::hex(self::mix($rgb, self::BLACK, .66)),
            'primary-300'  => self::hex(self::mix($rgb, self::WHITE, .30)),
            'primary-200'  => self::hex(self::mix($rgb, self::WHITE, .60)),
            'primary-100'  => self::hex(self::mix($rgb, self::WHITE, .80)),
            'primary-50'   => self::hex(self::mix($rgb, self::WHITE, .88)),
            'on-primary'   => self::luminance($rgb) > .5 ? '#1E1D1B' : '#FFFFFF',
        ];
    }

    public static function secondary(string $hex): array
    {
        $rgb = self::rgb($hex);

        return [
            'secondary'      => self::hex($rgb),
            'secondary-dark' => self::hex(self::mix($rgb, self::BLACK, .45)),
            'secondary-300'  => self::hex(self::mix($rgb, self::WHITE, .45)),
            'secondary-200'  => self::hex(self::mix($rgb, self::WHITE, .60)),
            'secondary-50'   => self::hex(self::mix($rgb, self::WHITE, .85)),
        ];
    }

    public static function tint(string $hex, float $amount): string
    {
        return self::hex(self::mix(self::rgb($hex), self::WHITE, $amount));
    }

    public static function mix(array $a, array $b, float $amount): array
    {
        return array_map(fn ($x, $y) => (int) round($x * (1 - $amount) + $y * $amount), $a, $b);
    }

    public static function rgb(string $hex): array
    {
        $hex = ltrim(self::normalize($hex) ?? '#000000', '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    public static function hex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', ...array_map(fn ($c) => max(0, min(255, $c)), $rgb));
    }

    /** WCAG relative luminance (0 = black, 1 = white). */
    public static function luminance(array $rgb): float
    {
        $channels = array_map(static function ($channel): float {
            $value = $channel / 255;
            return $value <= .04045 ? $value / 12.92 : (($value + .055) / 1.055) ** 2.4;
        }, $rgb);

        return .2126 * $channels[0] + .7152 * $channels[1] + .0722 * $channels[2];
    }
}
