<?php

namespace App\Builder\Blocks;

class ProductsCarouselBlock implements BlockInterface
{
    public function getType(): string { return 'products_carousel'; }

    public function getDefaultData(): array
    {
        return [
            'title' => 'Productos Destacados',
            'limit' => 8,
            'background_color' => '#ffffff',
            'text_color' => '#111827',
        ];
    }
}
