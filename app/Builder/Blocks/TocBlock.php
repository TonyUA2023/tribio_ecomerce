<?php

namespace App\Builder\Blocks;

class TocBlock extends AbstractBlock
{
    public function getType(): string
    {
        return 'toc';
    }

    public function validate(array $data): bool
    {
        return true;
    }

    public function sanitize(array $data): array
    {
        return [
            'content' => $data['content'] ?? 'Tabla de Contenidos',
            'color' => $data['color'] ?? '#111827',
            'background_color' => $data['background_color'] ?? '#f9fafb',
            'padding_x' => (int)($data['padding_x'] ?? 24),
            'padding_y' => (int)($data['padding_y'] ?? 24),
        ];
    }
}
