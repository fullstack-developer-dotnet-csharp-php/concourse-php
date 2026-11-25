<?php
declare(strict_types=1);

namespace App\Store;

use PDO;

final class MysqlCatalogStore implements CatalogStore
{
    private PDO $db;

    public function __construct(string $host, int $port, string $name, string $user, string $password)
    {
        $this->db = new PDO(
            'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4',
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->exec('CREATE TABLE IF NOT EXISTS attractions (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            name VARCHAR(500) NOT NULL,
            city VARCHAR(200) NULL,
            country VARCHAR(200) NULL,
            run_id VARCHAR(32) NOT NULL,
            doc LONGTEXT NOT NULL,
            KEY idx_name (name(100)),
            KEY idx_city (city),
            KEY idx_run (run_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->db->exec('CREATE TABLE IF NOT EXISTS catalog_meta (
            meta_key VARCHAR(100) NOT NULL PRIMARY KEY,
            meta_value TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function upsert(array $rows, string $runId): void
    {
        $stmt = $this->db->prepare('INSERT INTO attractions (id, name, city, country, run_id, doc) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name), city = VALUES(city), country = VALUES(country), run_id = VALUES(run_id), doc = VALUES(doc)');
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $stmt->execute([$row['id'], $row['name'], $row['city'], $row['country'], $runId, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function removeNotSeen(string $runId): int
    {
        $stmt = $this->db->prepare('DELETE FROM attractions WHERE run_id <> ?');
        $stmt->execute([$runId]);
        return $stmt->rowCount();
    }

    public function remove(string $id): void
    {
        $this->db->prepare('DELETE FROM attractions WHERE id = ?')->execute([$id]);
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM attractions')->fetchColumn();
    }

    public function all(): array
    {
        return $this->decode($this->db->query('SELECT doc FROM attractions ORDER BY name')->fetchAll());
    }

    public function search(string $q, int $limit): array
    {
        $like = '%' . addcslashes($q, '\\%_') . '%';
        $stmt = $this->db->prepare('SELECT doc FROM attractions WHERE name LIKE :q OR city LIKE :q OR country LIKE :q LIMIT ' . (int) $limit);
        $stmt->execute(['q' => $like]);
        return $this->decode($stmt->fetchAll());
    }

    public function meta(string $key): ?string
    {
        $stmt = $this->db->prepare('SELECT meta_value FROM catalog_meta WHERE meta_key = ?');
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    public function setMeta(string $key, string $value): void
    {
        $this->db->prepare('INSERT INTO catalog_meta (meta_key, meta_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)')->execute([$key, $value]);
    }

    private function decode(array $rows): array
    {
        return array_map(static fn(array $r) => json_decode($r['doc'], true, 512, JSON_THROW_ON_ERROR), $rows);
    }
}
