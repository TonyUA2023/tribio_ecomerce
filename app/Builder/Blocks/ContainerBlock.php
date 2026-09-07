<?php

namespace App\Builder\Blocks;

class ContainerBlock implements BlockInterface
{
    public function getType(): string { return 'container'; }

    public function getDefaultData(): array
    {
        return [
            'layout' => 'flex-col', // flex-col, flex-row, grid
            'background_color' => 'transparent',
            'padding_x' => 16,
            'padding_y' => 16,
            'blocks' => []
        ];
    }
}
