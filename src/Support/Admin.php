<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Admin
{
    private const ATTEMPTS = __DIR__ . '/../../storage/login-attempts.json';
    private const TOKEN = __DIR__ . '/../../storage/setup-token.txt';
    private const MAX_FAILURES = 5;
    private const LOCK_SECONDS = 900;

    public static function isConfigured(): bool
    {
        return Settings::hasSaved('admin.password_hash');
    }

    public static function isLoggedIn(): bool
    {
        if (!isset($_COOKIE['fxsid'])) {
            return false;
        }
        Session::start();
        return ($_SESSION['admin'] ?? false) === true;
    }

    public static function require(): ?Response
    {
        if (!self::isConfigured()) {
            return Response::redirect('/admin/setup');
        }
        if (!self::isLoggedIn()) {
            return Response::redirect('/admin/login?next=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/admin/settings')));
        }
        return null;
    }

    public static function setupTokenPath(): string
    {
        return realpath(dirname(self::TOKEN)) ? realpath(dirname(self::TOKEN)) . DIRECTORY_SEPARATOR . basename(self::TOKEN) : self::TOKEN;
    }

    public static function ensureSetupToken(): void
    {
        if (!is_file(self::TOKEN)) {
            $dir = dirname(self::TOKEN);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Cannot create ' . $dir);
            }
            if (file_put_contents(self::TOKEN, bin2hex(random_bytes(16))) === false) {
                throw new RuntimeException('Cannot write the setup token file ' . self::TOKEN);
            }
        }
    }

    public static function completeSetup(string $token, string $password): void
    {
        if (self::isConfigured()) {
            throw new ForbiddenException('Admin setup is already complete.');
        }
        self::guardAttempts();
        self::ensureSetupToken();
        if (!hash_equals(trim((string) file_get_contents(self::TOKEN)), trim($token))) {
            self::recordFailure();
            throw new ForbiddenException('The setup token is not correct. Open the file ' . self::setupTokenPath() . ' on the server and copy its contents.');
        }
        self::validatePassword($password);
        Settings::set(['admin.password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        if (!unlink(self::TOKEN)) {
            Log::warning('Setup token file could not be deleted', ['path' => self::TOKEN]);
        }
        self::login();
        Log::notice('Admin password created');
    }

    public static function attempt(string $password): void
    {
        self::guardAttempts();
        $hash = (string) (Settings::overrides()['admin.password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            self::recordFailure();
            Log::warning('Admin login failed');
            throw new ForbiddenException('The password is not correct.');
        }
        self::clearFailures();
        self::login();
        Log::notice('Admin logged in');
    }

    public static function changePassword(string $password): void
    {
        self::validatePassword($password);
        Settings::set(['admin.password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        Log::notice('Admin password changed');
    }

    public static function logout(): void
    {
        Session::start();
        $_SESSION = [];
        session_regenerate_id(true);
        Log::notice('Admin logged out');
    }

    private static function login(): void
    {
        Session::start();
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
    }

    private static function validatePassword(string $password): void
    {
        if (mb_strlen($password) < 10) {
            throw new ForbiddenException('The admin password must be at least 10 characters.');
        }
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
