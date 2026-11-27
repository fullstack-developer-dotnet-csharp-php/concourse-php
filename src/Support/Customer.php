<?php
declare(strict_types=1);

namespace App\Support;

final class Customer
{
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

    public static function login(array $profile): void
    {
        Session::start();
        session_regenerate_id(true);
        $_SESSION['customer'] = [
            'sub' => (string) $profile['sub'],
            'email' => (string) $profile['email'],
            'name' => (string) $profile['name'],
            'picture' => (string) $profile['picture'],
        ];
        Log::notice('Customer logged in with Google');
    }

    public static function logout(): void
    {
        Session::start();
        unset($_SESSION['customer']);
        session_regenerate_id(true);
        Log::notice('Customer logged out');
    }
}
