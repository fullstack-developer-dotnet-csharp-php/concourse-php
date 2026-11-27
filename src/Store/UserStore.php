<?php
declare(strict_types=1);

namespace App\Store;

interface UserStore
{
    public function findByEmail(string $email): ?array;

    public function findBySub(string $sub): ?array;

    public function findById(string $id): ?array;

    public function insert(array $user): array;

    public function update(string $id, array $changes): void;
}
