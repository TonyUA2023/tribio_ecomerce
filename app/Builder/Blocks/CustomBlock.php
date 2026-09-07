<?php

namespace App\Builder\Blocks;

class CustomBlock implements BlockInterface
{
    public function getType(): string { return 'custom'; }

    public function getDefaultData(): array
    {
        return [
            'background_color' => '#ffffff',
            'blocks' => []
        ];
    }
}
