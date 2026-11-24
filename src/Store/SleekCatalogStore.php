<?php
declare(strict_types=1);

namespace App\Store;

use SleekDB\Store;

final class SleekCatalogStore implements CatalogStore
{
    private Store $attractions;
    private Store $meta;

    public function __construct(string $dir)
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Catalog directory cannot be created: ' . $dir);
        }
        $options = ['auto_cache' => false, 'timeout' => false];
        $this->attractions = new Store('attractions', $dir, $options);
        $this->meta = new Store('meta', $dir, $options);
    }

    public function upsert(array $rows, string $runId): void
    {
        $known = [];
        foreach ($this->attractions->createQueryBuilder()->select(['_id', 'id'])->getQuery()->fetch() as $doc) {
            $known[$doc['id']] = $doc['_id'];
        }
        $docs = [];
        foreach ($rows as $row) {
            $row['_run'] = $runId;
            if (isset($known[$row['id']])) {
                $row['_id'] = $known[$row['id']];
            }
            $docs[] = $row;
        }
        $this->attractions->updateOrInsertMany($docs);
    }

    public function removeNotSeen(string $runId): int
    {
        $stale = $this->attractions->createQueryBuilder()->where(['_run', '!=', $runId])->getQuery()->fetch();
        foreach ($stale as $doc) {
            $this->attractions->deleteById($doc['_id']);
        }
        return count($stale);
    }

    public function count(): int
    {
        return $this->attractions->count();
    }

    public function all(): array
    {
        return $this->attractions->findAll();
    }

    public function search(string $q, int $limit): array
    {
        $like = '%' . str_replace(['%', '_'], '', $q) . '%';
        return $this->attractions->createQueryBuilder()
            ->where(['name', 'LIKE', $like])
            ->orWhere(['city', 'LIKE', $like])
            ->orWhere(['country', 'LIKE', $like])
            ->limit($limit)
            ->getQuery()
            ->fetch();
    }

    public function meta(string $key): ?string
    {
        $doc = $this->meta->findOneBy(['key', '=', $key]);
        return $doc === null ? null : (string) $doc['value'];
    }

    public function setMeta(string $key, string $value): void
    {
        $doc = $this->meta->findOneBy(['key', '=', $key]);
        $row = ['key' => $key, 'value' => $value];
        if ($doc !== null) {
            $row['_id'] = $doc['_id'];
        }
        $this->meta->updateOrInsert($row);
    }
}
