<?php
declare(strict_types=1);

namespace App\Store;

interface CatalogStore
{
    public function upsert(array $rows, string $runId): void;

    public function removeNotSeen(string $runId): int;

    public function remove(string $id): void;

    public function count(): int;

    public function all(): array;

    public function search(string $q, int $limit): array;

    public function meta(string $key): ?string;

    public function setMeta(string $key, string $value): void;
}
