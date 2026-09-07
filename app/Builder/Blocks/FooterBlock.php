<?php

namespace App\Builder\Blocks;

class FooterBlock implements BlockInterface
{
    public function getType(): string { return 'footer'; }

    public function getDefaultData(): array
    {
        return [
            'footer_bg_color' => '#111827',
            'footer_text_color' => '#9CA3AF',
            'heading_color' => '#E50914',
            'col1_text' => 'Tu tienda de confianza con los mejores productos.',
            'col2_title' => 'NAVEGACIÓN',
            'link_1' => 'INICIO',
            'link_2' => 'CATÁLOGO',
            'col3_title' => 'CONTACTO',
            'whatsapp' => '++51902699916',
            'address' => 'PERÚ',
            'col4_title' => 'REDES SOCIALES',
            'facebook_link' => '',
            'instagram_link' => '',
            'tiktok_link' => '',
            'twitter_link' => '',
            'email' => '',
            'phone' => '',
        ];
    }
}
