<?php
declare(strict_types=1);

namespace App\Store;

use PDO;

final class MysqlUserStore implements UserStore
{
    private const COLUMNS = ['password_hash', 'first', 'last', 'google_sub', 'picture', 'last_login_at'];

    private PDO $db;

    public function __construct(string $host, int $port, string $name, string $user, string $password)
    {
        $this->db = new PDO(
            'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4',
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->db->exec('CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(32) NOT NULL PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NULL,
            first VARCHAR(120) NOT NULL,
            last VARCHAR(120) NOT NULL,
            google_sub VARCHAR(64) NULL,
            picture VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            last_login_at DATETIME NULL,
            UNIQUE KEY idx_email (email),
            UNIQUE KEY idx_google_sub (google_sub)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([strtolower($email)]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findBySub(string $sub): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE google_sub = ?');
        $stmt->execute([$sub]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function insert(array $user): array
    {
        $id = bin2hex(random_bytes(12));
        $user['id'] = $id;
        $user['email'] = strtolower($user['email']);
        $user['created_at'] = gmdate('Y-m-d H:i:s');
        $stmt = $this->db->prepare('INSERT INTO users (id, email, password_hash, first, last, google_sub, picture, created_at, last_login_at)
            VALUES (:id, :email, :password_hash, :first, :last, :google_sub, :picture, :created_at, :last_login_at)');
        $stmt->execute([
            'id' => $id,
            'email' => $user['email'],
            'password_hash' => $user['password_hash'] ?? null,
            'first' => $user['first'],
            'last' => $user['last'],
            'google_sub' => $user['google_sub'] ?? null,
            'picture' => $user['picture'] ?? null,
            'created_at' => $user['created_at'],
            'last_login_at' => $user['last_login_at'] ?? null,
        ]);
        return $this->findById($id) ?? $user;
    }

    public function update(string $id, array $changes): void
    {
        $set = [];
        $params = ['id' => $id];
        foreach ($changes as $key => $value) {
            if (!in_array($key, self::COLUMNS, true)) {
                continue;
            }
            $set[] = $key . ' = :' . $key;
            $params[$key] = $value;
        }
        if ($set === []) {
            return;
        }
        $this->db->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
    }
}
