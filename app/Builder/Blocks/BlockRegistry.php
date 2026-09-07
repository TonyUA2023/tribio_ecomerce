<?php

namespace App\Builder\Blocks;

class BlockRegistry
{
    protected static array $blocks = [];

    public static function register(string $type, string $class): void
    {
        static::$blocks[$type] = $class;
    }

    public static function getBlock(string $type): ?BlockInterface
    {
        if (isset(static::$blocks[$type])) {
            $class = static::$blocks[$type];
            return new $class();
        }

        // Try to auto-resolve by convention
        $className = 'App\\Builder\\Blocks\\' . \Illuminate\Support\Str::studly($type) . 'Block';
        if (class_exists($className)) {
            return new $className();
        }

        return null;
    }
}
