<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Config
{
    private static array $data = [];

    public static function load(array $data): void
    {
        self::$data = $data;
    }

    public static function get(string $path): mixed
    {
        $node = self::$data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                throw new RuntimeException("Missing config value: $path");
            }
            $node = $node[$part];
        }
        return $node;
    }

    public static function string(string $path): string
    {
        return (string) self::get($path);
    }

    public static function int(string $path): int
    {
        return (int) self::get($path);
    }

    public static function bool(string $path): bool
    {
        return (bool) self::get($path);
    }
}
