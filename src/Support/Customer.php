<?php
declare(strict_types=1);

namespace App\Support;

use App\Store\UserStoreFactory;
use RuntimeException;

final class Customer
{
    private const ATTEMPTS = __DIR__ . '/../../storage/customer-login-attempts.json';
    private const MAX_FAILURES = 5;
    private const LOCK_SECONDS = 900;

    public static function isLoggedIn(): bool
    {
        if (!isset($_COOKIE['fxsid'])) {
            return false;
        }
        Session::start();
        return isset($_SESSION['customer']) && is_array($_SESSION['customer']);
    }

    public static function user(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }
        return $_SESSION['customer'];
    }

    public static function require(): ?Response
    {
        if (!self::isLoggedIn()) {
            return Response::redirect('/login?next=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/')));
        }
        return null;
    }

    public static function register(string $email, string $password, string $first, string $last): void
    {
        $email = strtolower(trim($email));
        $first = trim($first);
        $last = trim($last);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }
        if (mb_strlen($password) < 8) {
            throw new RuntimeException('The password must be at least 8 characters.');
        }
        if ($first === '' || $last === '') {
            throw new RuntimeException('First and last name are required.');
        }
        $store = UserStoreFactory::make();
        if ($store->findByEmail($email) !== null) {
            throw new RuntimeException('An account with this email already exists. Please sign in instead.');
        }
        $now = gmdate('c');
        $row = $store->insert([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'first' => $first,
            'last' => $last,
            'google_sub' => null,
            'picture' => null,
            'created_at' => $now,
            'last_login_at' => $now,
        ]);
        self::start($row);
        Log::notice('Customer account created');
    }

    public static function attempt(string $email, string $password): void
    {
        self::guardAttempts();
        $email = strtolower(trim($email));
        $store = UserStoreFactory::make();
        $row = $store->findByEmail($email);
        if ($row === null || empty($row['password_hash']) || !password_verify($password, (string) $row['password_hash'])) {
            self::recordFailure();
            Log::warning('Customer login failed');
            throw new ForbiddenException('That email and password do not match.');
        }
        self::clearFailures();
        $store->update((string) $row['id'], ['last_login_at' => gmdate('c')]);
        self::start($row);
        Log::notice('Customer logged in');
    }

    public static function loginWithGoogle(array $profile): void
    {
        $email = strtolower(trim((string) $profile['email']));
        if ($email === '') {
            throw new RuntimeException('Google did not share an email address.');
        }
        $store = UserStoreFactory::make();
        $row = $store->findBySub((string) $profile['sub']);
        if ($row === null) {
            $row = $store->findByEmail($email);
            if ($row !== null) {
                $store->update((string) $row['id'], ['google_sub' => $profile['sub'], 'picture' => $profile['picture']]);
                $row['google_sub'] = $profile['sub'];
                $row['picture'] = $profile['picture'];
            }
        }
        if ($row === null) {
            $first = (string) $profile['first'];
            $last = (string) $profile['last'];
            if ($first === '' && $last === '') {
                $parts = explode(' ', trim((string) $profile['name']), 2);
                $first = $parts[0] ?? 'Google';
                $last = $parts[1] ?? 'user';
            }
            $now = gmdate('c');
            $row = $store->insert([
                'email' => $email,
                'password_hash' => null,
                'first' => $first !== '' ? $first : 'Google',
                'last' => $last !== '' ? $last : 'user',
                'google_sub' => $profile['sub'],
                'picture' => $profile['picture'],
                'created_at' => $now,
                'last_login_at' => $now,
            ]);
        } else {
            $store->update((string) $row['id'], ['last_login_at' => gmdate('c')]);
        }
        self::start($row);
        Log::notice('Customer logged in with Google');
    }

    public static function logout(): void
    {
        Session::start();
        unset($_SESSION['customer']);
        session_regenerate_id(true);
        Log::notice('Customer logged out');
    }

    private static function start(array $row): void
    {
        Session::start();
        session_regenerate_id(true);
        $_SESSION['customer'] = [
            'id' => (string) $row['id'],
            'email' => (string) $row['email'],
            'first' => (string) $row['first'],
            'last' => (string) $row['last'],
            'name' => trim($row['first'] . ' ' . $row['last']),
            'picture' => (string) ($row['picture'] ?? ''),
        ];
    }

    private static function attempts(): array
    {
        if (!is_file(self::ATTEMPTS)) {
            return [];
        }
        $data = json_decode((string) file_get_contents(self::ATTEMPTS), true);
        return is_array($data) ? $data : [];
    }

    private static function saveAttempts(array $data): void
    {
        if (file_put_contents(self::ATTEMPTS, json_encode($data), LOCK_EX) === false) {
            throw new RuntimeException('Cannot write ' . self::ATTEMPTS);
        }
    }

    private static function guardAttempts(): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $row = self::attempts()[$ip] ?? null;
        if ($row !== null && $row['count'] >= self::MAX_FAILURES && time() - $row['last'] < self::LOCK_SECONDS) {
            $wait = (int) ceil((self::LOCK_SECONDS - (time() - $row['last'])) / 60);
            throw new ForbiddenException('Too many failed attempts. Try again in ' . $wait . ' minute(s).');
        }
    }

    private static function recordFailure(): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $all = self::attempts();
        $row = $all[$ip] ?? ['count' => 0, 'last' => 0];
        $row['count'] = time() - $row['last'] >= self::LOCK_SECONDS ? 1 : $row['count'] + 1;
        $row['last'] = time();
        $all[$ip] = $row;
        self::saveAttempts($all);
    }

    private static function clearFailures(): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $all = self::attempts();
        unset($all[$ip]);
        self::saveAttempts($all);
    }
}
