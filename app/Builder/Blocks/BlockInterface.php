<?php

namespace App\Builder\Blocks;

interface BlockInterface
{
    public function getType(): string;
    public function getDefaultData(): array;
}
