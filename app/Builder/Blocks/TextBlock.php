<?php

namespace App\Builder\Blocks;

class TextBlock implements BlockInterface
{
    public function getType(): string { return 'text'; }

    public function getDefaultData(): array
    {
        return [
            'title' => 'Sobre Nosotros',
            'content' => 'Escribe aquí la información sobre tu negocio.',
            'background_color' => '#ffffff',
            'text_color' => '#111827',
        ];
    }
}
