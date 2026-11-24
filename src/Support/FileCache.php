<?php
declare(strict_types=1);

namespace App\Support;

final class FileCache
{
    public function __construct(private string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }

    public function get(string $key, int $ttl): ?array
    {
        $file = $this->path($key);
        if ($ttl <= 0 || !is_file($file) || filemtime($file) < time() - $ttl) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : null;
    }

    public function put(string $key, array $value): void
    {
        file_put_contents($this->path($key), json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . sha1($key) . '.json';
    }
}
