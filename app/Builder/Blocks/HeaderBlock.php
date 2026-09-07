<?php

namespace App\Builder\Blocks;

class HeaderBlock implements BlockInterface
{
    public function getType(): string { return 'header'; }

    public function getDefaultData(): array
    {
        return [
            'background_color' => '#FFFFFF',
            'blocks' => [
                [
                    'type' => 'container',
                    'full_width' => true,
                    'layout' => 'flex-col',
                    'background_color' => 'transparent',
                    'padding_y' => 0,
                    'padding_x' => 0,
                    'blocks' => [
                        [
                            'type' => 'container',
                            'layout' => 'flex-row',
                            'alignment' => 'center',
                            'background_color' => 'transparent',
                            'padding_y' => 16,
                            'padding_x' => 24,
                            'blocks' => []
                        ]
                    ]
                ]
            ]
        ];
    }
}
