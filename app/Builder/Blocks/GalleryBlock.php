<?php

namespace App\Builder\Blocks;

class GalleryBlock implements BlockInterface
{
    public function getType(): string { return 'gallery'; }

    public function getDefaultData(): array
    {
        return [
            'title' => 'Nuestra Galería',
            'background_color' => '#ffffff',
            'text_color' => '#111827',
        ];
    }
}
