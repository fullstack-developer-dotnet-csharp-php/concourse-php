<?php
declare(strict_types=1);

namespace App\Store;

use SleekDB\Store;

final class SleekUserStore implements UserStore
{
    private Store $users;

    public function __construct(string $dir)
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('User directory cannot be created: ' . $dir);
        }
        $this->users = new Store('users', $dir, ['auto_cache' => false, 'timeout' => false]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->normalize($this->users->findOneBy(['email', '=', strtolower($email)]));
    }

    public function findBySub(string $sub): ?array
    {
        return $this->normalize($this->users->findOneBy(['google_sub', '=', $sub]));
    }

    public function findById(string $id): ?array
    {
        if (!ctype_digit($id)) {
            return null;
        }
        return $this->normalize($this->users->findById((int) $id));
    }

    public function insert(array $user): array
    {
        $user['email'] = strtolower($user['email']);
        return $this->normalize($this->users->insert($user)) ?? [];
    }

    public function update(string $id, array $changes): void
    {
        $this->users->updateById((int) $id, $changes);
    }

    private function normalize(?array $doc): ?array
    {
        if ($doc === null) {
            return null;
        }
        $doc['id'] = (string) $doc['_id'];
        return $doc;
    }
}
