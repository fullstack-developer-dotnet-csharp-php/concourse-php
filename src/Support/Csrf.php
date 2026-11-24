<?php
declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(): void
    {
        Session::start();
        $sent = input($_POST, '_csrf');
        if ($sent === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
            Log::warning('CSRF check failed', ['path' => $_SERVER['REQUEST_URI'] ?? '']);
            throw new ForbiddenException('The security token is missing or has expired. Go back, reload the page and try again.');
        }
    }
}
