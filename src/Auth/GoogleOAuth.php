<?php
declare(strict_types=1);

namespace App\Auth;

use App\Support\Log;
use RuntimeException;

final class GoogleOAuth
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function __construct(private string $clientId, private string $clientSecret)
    {
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Google client ID and secret are not set. Add them on the settings page.');
        }
    }

    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): string
    {
        $data = $this->call('POST', self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ], true);
        $token = (string) ($data['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Google did not return an access token.');
        }
        return $token;
    }

    public function userInfo(string $accessToken): array
    {
        $data = $this->call('GET', self::USERINFO_URL, null, false, $accessToken);
        $sub = (string) ($data['sub'] ?? '');
        if ($sub === '') {
            throw new RuntimeException('Google did not return a profile.');
        }
        return [
            'sub' => $sub,
            'email' => (string) ($data['email'] ?? ''),
            'first' => (string) ($data['given_name'] ?? ''),
            'last' => (string) ($data['family_name'] ?? ''),
            'name' => (string) ($data['name'] ?? ($data['email'] ?? 'Google user')),
            'picture' => (string) ($data['picture'] ?? ''),
        ];
    }

    private function call(string $method, string $url, ?array $form, bool $hideBody = false, ?string $bearer = null): array
    {
        $headers = ['Accept: application/json'];
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($form !== null) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($form);
        }
        curl_setopt_array($ch, $opts);
        $t = microtime(true);
        $payload = curl_exec($ch);
        $millis = (microtime(true) - $t) * 1000;
        if ($payload === false) {
            $error = curl_error($ch);
            Log::apiCall($method, $url, 0, $millis, 0, null, null, null, 'Google OAuth: ' . $error);
            throw new RuntimeException('Could not reach Google: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        // The request body can carry the client secret or auth code; never write it to the log.
        Log::apiCall($method, $url, $status, $millis, strlen((string) $payload), $hideBody ? null : (string) $payload, null, null);
        $data = json_decode((string) $payload, true);
        if (!is_array($data)) {
            throw new RuntimeException('Google returned a response that is not JSON.');
        }
        if ($status >= 400) {
            throw new RuntimeException((string) ($data['error_description'] ?? $data['error'] ?? 'Google sign-in failed (HTTP ' . $status . ').'));
        }
        return $data;
    }
}
