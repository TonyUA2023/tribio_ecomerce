<?php

namespace App\Services\Marketing\Meta;

use Illuminate\Support\Facades\Storage;

/**
 * Meta's catalog accepts JPEG and PNG only, while product photos may be uploaded as
 * WebP. WebP images are served to Meta through /feed-img/{path}.jpg, converted once
 * and kept under storage/app/public/feed-cache (a new upload gets a new file name).
 */
class FeedImage
{
    private const CACHE_DIR = 'feed-cache';

    public function url(string $storagePath): string
    {
        if (!$this->isWebp($storagePath)) {
            return asset('storage/' . ltrim($storagePath, '/'));
        }

        return route('marketing.feed-image', ['path' => ltrim($storagePath, '/') . '.jpg']);
    }

    /** Absolute path of the JPEG copy, or null when the source can't be served/converted. */
    public function jpeg(string $storagePath): ?string
    {
        $storagePath = ltrim($storagePath, '/');
        $disk = Storage::disk('public');

        if (!$this->isWebp($storagePath) || str_contains($storagePath, '..') || !str_starts_with($storagePath, 'stores/')
            || !$disk->exists($storagePath)) {
            return null;
        }

        $target = self::CACHE_DIR . '/' . sha1($storagePath . '|' . $disk->lastModified($storagePath)) . '.jpg';
        if ($disk->exists($target)) {
            return $disk->path($target);
        }
        if (!function_exists('imagecreatefromwebp')) {
            return null;
        }

        $source = @imagecreatefromwebp($disk->path($storagePath));
        if (!$source) {
            return null;
        }
        // JPEG has no transparency: flatten onto white, like a product photo background.
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 88);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        $disk->put($target, $jpeg);

        return $disk->path($target);
    }

    private function isWebp(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp';
    }
}
