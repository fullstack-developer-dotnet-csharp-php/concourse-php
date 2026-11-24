<?php
declare(strict_types=1);

namespace App\Payments;

use RuntimeException;

final class PendingStore
{
    public function __construct(private string $dir)
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create payments folder ' . $dir);
        }
    }

    public function create(array $record): string
    {
        $id = 'FX' . strtoupper(bin2hex(random_bytes(10)));
        $record['id'] = $id;
        $record['status'] = 'created';
        $record['createdAt'] = gmdate('c');
        $this->write($id, $record);
        return $id;
    }

    public function get(string $id): ?array
    {
        $file = $this->path($id);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException('Payment record ' . $id . ' is not valid JSON.');
        }
        return $data;
    }

    public function mutate(string $id, callable $change): array
    {
        $file = $this->path($id);
        if (!is_file($file)) {
            throw new RuntimeException('Unknown payment reference ' . $id);
        }
        $handle = fopen($file, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('Cannot lock payment record ' . $id);
        }
        try {
            $record = json_decode((string) stream_get_contents($handle), true);
            if (!is_array($record)) {
                throw new RuntimeException('Payment record ' . $id . ' is not valid JSON.');
            }
            $record = $change($record);
            $record['updatedAt'] = gmdate('c');
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            fflush($handle);
            return $record;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function write(string $id, array $record): void
    {
        if (file_put_contents($this->path($id), json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Cannot write payment record ' . $id);
        }
    }

    private function path(string $id): string
    {
        if (!preg_match('/^FX[0-9A-F]{20}$/', $id)) {
            throw new \App\Support\NotFoundException('Invalid payment reference.');
        }
        return $this->dir . DIRECTORY_SEPARATOR . $id . '.json';
    }
}
