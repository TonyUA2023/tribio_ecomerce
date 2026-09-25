<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class LogoPaletteService
{
    /**
     * Sample an uploaded logo locally. Transparent pixels and near-white canvas
     * are ignored so the mark, rather than its backdrop, determines the colors.
     */
    public function extract(string $file): ?array
    {
        if (!extension_loaded('gd') || !is_file($file)) {
            return null;
        }

        $dimensions = @getimagesize($file);
        if (!$dimensions || $dimensions[0] < 1 || $dimensions[1] < 1
            || $dimensions[0] * $dimensions[1] > 16_000_000) {
            return null;
        }

        $image = @imagecreatefromstring(file_get_contents($file));
        if (!$image) {
            return null;
        }

        try {
            $buckets = [];
            $lightPixels = 0;
            $step = max(1, (int) ceil(max(imagesx($image), imagesy($image)) / 96));
            $trueColor = imageistruecolor($image);

            for ($y = 0; $y < imagesy($image); $y += $step) {
                for ($x = 0; $x < imagesx($image); $x += $step) {
                    $pixel = imagecolorat($image, $x, $y);
                    $rgba = $trueColor
                        ? ['red' => ($pixel >> 16) & 255, 'green' => ($pixel >> 8) & 255,
                            'blue' => $pixel & 255, 'alpha' => ($pixel >> 24) & 127]
                        : imagecolorsforindex($image, $pixel);

                    if ($rgba['alpha'] > 80) {
                        continue;
                    }

                    $rgb = [$rgba['red'], $rgba['green'], $rgba['blue']];
                    $max = max($rgb);
                    $min = min($rgb);
                    // Reject white canvas and essentially invisible highlights.
                    if ($min > 242 || ($min > 226 && $max - $min < 20)) {
                        $lightPixels++;
                        continue;
                    }

                    $key = implode('-', array_map(fn ($channel) => intdiv($channel, 32), $rgb));
                    if (!isset($buckets[$key])) {
                        $buckets[$key] = ['count' => 0, 'sum' => [0, 0, 0]];
                    }
                    $buckets[$key]['count']++;
                    foreach ($rgb as $i => $channel) {
                        $buckets[$key]['sum'][$i] += $channel;
                    }
                }
            }

            if (!$buckets) {
                return $lightPixels ? [
                    'primary' => '#263744', 'secondary' => '#CEDCE4',
                    'background' => '#F7FAFC', 'text' => '#17212B', 'button_text' => '#FFFFFF',
                ] : null;
            }

            $colors = [];
            foreach ($buckets as $bucket) {
                $rgb = array_map(fn ($sum) => (int) round($sum / $bucket['count']), $bucket['sum']);
                $saturation = (max($rgb) - min($rgb)) / max(1, max($rgb));
                $colors[] = [
                    'rgb' => $rgb,
                    'count' => $bucket['count'],
                    'saturation' => $saturation,
                    'score' => $bucket['count'] * ($saturation >= .18 ? 1 + $saturation : .55),
                ];
            }
            usort($colors, fn ($a, $b) => $b['score'] <=> $a['score']);

            $visibleCount = array_sum(array_column($colors, 'count'));
            $vivid = array_values(array_filter($colors, fn ($color) => $color['saturation'] >= .18
                && $color['count'] >= $visibleCount * .04));
            $primaryChoice = $vivid[0] ?? $colors[0];
            $primary = $primaryChoice['rgb'];
            $second = null;
            foreach ($colors as $candidate) {
                if ($candidate === $primaryChoice || $candidate['count'] < $primaryChoice['count'] * .12) {
                    continue;
                }
                if (self::distance($primary, $candidate['rgb']) > 80) {
                    $second = $candidate['rgb'];
                    break;
                }
            }

            $accent = $primary;
            while (self::contrast($accent, [255, 255, 255]) < 4.5) {
                $accent = self::blend($accent, [0, 0, 0], .12);
            }

            return [
                'primary' => self::hex($accent),
                'secondary' => self::hex($second ?? self::blend($primary, [255, 255, 255], .38)),
                'background' => self::hex(self::blend($primary, [255, 255, 255], .92)),
                'text' => '#17212B',
                'button_text' => '#FFFFFF',
            ];
        } finally {
            imagedestroy($image);
        }
    }

    /** Apply to the hero without touching the owner's copy, imagery or layout. */
    public function applyToHero(array $data, array $palette): array
    {
        $previousButton = $data['palette_button_color'] ?? '#111827';
        $data['background_color'] = $palette['background'];
        $data['text_color'] = $palette['text'];
        $data['palette_mode'] = 'auto';
        $data['palette_button_color'] = $palette['primary'];
        $data['blocks'] = is_array($data['blocks'] ?? null) ? $data['blocks'] : [];

        $recolor = function (array &$blocks) use (&$recolor, $palette, $previousButton): void {
            foreach ($blocks as &$block) {
                if (($block['type'] ?? null) === 'button'
                    && (!isset($block['background_color']) || strcasecmp($block['background_color'], $previousButton) === 0)) {
                    $block['background_color'] = $palette['primary'];
                    $block['text_color'] = $palette['button_text'];
                }
                if (!empty($block['blocks']) && is_array($block['blocks'])) {
                    $recolor($block['blocks']);
                }
            }
            unset($block);
        };
        $recolor($data['blocks']);

        return $data;
    }

    private static function blend(array $a, array $b, float $amount): array
    {
        return array_map(fn ($x, $y) => (int) round($x * (1 - $amount) + $y * $amount), $a, $b);
    }

    private static function distance(array $a, array $b): float
    {
        return sqrt(($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2);
    }

    private static function hex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', ...$rgb);
    }

    private static function contrast(array $a, array $b): float
    {
        $luminance = static function (array $rgb): float {
            $channels = array_map(static function ($channel): float {
                $value = $channel / 255;
                return $value <= .04045 ? $value / 12.92 : (($value + .055) / 1.055) ** 2.4;
            }, $rgb);

            return .2126 * $channels[0] + .7152 * $channels[1] + .0722 * $channels[2];
        };

        return ($luminance($b) + .05) / ($luminance($a) + .05);
    }

    /**
     * Whether the logo sits on a transparent canvas (corners and edge midpoints see-through).
     * Only such logos can be recolored to a white silhouette; a JPG or a PNG with a solid
     * backdrop would turn into a white rectangle. SVGs are assumed transparent.
     * Cached per file + modification time, so a re-upload is re-checked.
     */
    public function hasTransparentBackground(string $file): bool
    {
        if (!is_file($file)) {
            return false;
        }
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg') {
            return true;
        }

        return Cache::rememberForever('logo-transparent:' . md5($file . '|' . filemtime($file)), function () use ($file) {
            $dimensions = @getimagesize($file);
            if (!extension_loaded('gd') || !$dimensions || $dimensions[0] * $dimensions[1] > 16_000_000) {
                return false;
            }
            $image = @imagecreatefromstring(file_get_contents($file));
            if (!$image) {
                return false;
            }
            try {
                [$w, $h] = [imagesx($image) - 1, imagesy($image) - 1];
                $points = [[0, 0], [$w, 0], [0, $h], [$w, $h], [intdiv($w, 2), 0], [intdiv($w, 2), $h], [0, intdiv($h, 2)], [$w, intdiv($h, 2)]];
                $clear = 0;
                foreach ($points as [$x, $y]) {
                    // GD alpha: 0 = opaque … 127 = fully transparent.
                    $color = imagecolorat($image, $x, $y);
                    $alpha = imageistruecolor($image) ? ($color >> 24) & 0x7F : imagecolorsforindex($image, $color)['alpha'];
                    if ($alpha >= 100) {
                        $clear++;
                    }
                }

                return $clear >= 6;
            } finally {
                imagedestroy($image);
            }
        });
    }
}
