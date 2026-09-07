<?php

namespace App\Builder\Blocks;

class HeroBlock implements BlockInterface
{
    public function getType(): string { return 'hero'; }

    public function getDefaultData(): array
    {
        return [
            'alignment' => 'center',
            'size' => 'normal',
            'background_color' => '#f3f4f6',
            'background_image' => '',
            'carousel_images' => [],
            'text_color' => '#111827',
            'blocks' => [
                [
                    'id' => uniqid('block_'),
                    'type' => 'title',
                    'tag' => 'h1',
                    'content' => 'Bienvenido a nuestra tienda',
                    'text_align' => 'center',
                    'is_bold' => true,
                ],
                [
                    'id' => uniqid('block_'),
                    'type' => 'paragraph',
                    'content' => 'Descubre nuestros productos',
                    'text_align' => 'center',
                ],
                [
                    'id' => uniqid('block_'),
                    'type' => 'button',
                    'content' => 'Comprar ahora',
                    'url' => '#',
                    'text_align' => 'center',
                    'background_color' => '#111827',
                    'text_color' => '#ffffff',
                ]
            ]
        ];
    }
}
