<?php

namespace App\Builder\Blocks;

class CategoriesBlock implements BlockInterface
{
    public function getType(): string { return 'categories'; }

    public function getDefaultData(): array
    {
        return [
            'title' => 'Explora por Categorías',
            'layout' => 'grid', // 'grid' o 'carousel'
            'background_color' => '#f9fafb',
            'text_color' => '#111827',
        ];
    }
}
