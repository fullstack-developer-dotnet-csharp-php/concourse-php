<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\GoogleOAuth;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\Customer;
use App\Support\ForbiddenException;
use App\Support\Log;
use App\Support\Response;
use App\Support\Session;
use Throwable;

final class AuthController
{
    public function start(): Response
    {
        Session::start();
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_next'] = $this->next();
        $google = new GoogleOAuth(Config::string('auth.google.client_id'), Config::string('auth.google.client_secret'));
        return Response::redirect($google->authorizeUrl($this->redirectUri(), $state));
    }

    public function callback(): Response
    {
        Session::start();
        $next = (string) ($_SESSION['oauth_next'] ?? '/');
        unset($_SESSION['oauth_next']);
        $expectedState = (string) ($_SESSION['oauth_state'] ?? '');
        unset($_SESSION['oauth_state']);
        $state = input($_GET, 'state');
        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new ForbiddenException('The sign-in request has expired. Please try again.');
        }
        $code = input($_GET, 'code');
        if ($code === '') {
            $error = input($_GET, 'error');
            throw new ForbiddenException($error !== '' ? 'Google sign-in was cancelled.' : 'Google did not return a sign-in code.');
        }
        try {
            $google = new GoogleOAuth(Config::string('auth.google.client_id'), Config::string('auth.google.client_secret'));
            $accessToken = $google->exchangeCode($code, $this->redirectUri());
            $profile = $google->userInfo($accessToken);
        } catch (Throwable $e) {
            Log::exception($e, 'Google sign-in failed');
            throw new ForbiddenException('Google sign-in did not work. Please try again.');
        }
        Customer::login($profile);
        return Response::redirect($next);
    }

    public function logout(): Response
    {
        Csrf::verify();
        Customer::logout();
        return Response::redirect($this->next());
    }

    private function redirectUri(): string
    {
        return abs_url('/auth/google/callback');
    }

    private function next(): string
    {
        $next = input($_POST + $_GET, 'next');
        return str_starts_with($next, '/') && !str_starts_with($next, '//') ? $next : '/';
    }
}
