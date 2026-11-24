<?php
declare(strict_types=1);

namespace App\Payments;

use App\Support\Log;
use RuntimeException;

final class Cashfree
{
    public function __construct(
        private string $mode,
        private string $appId,
        private string $secret,
        private string $apiVersion
    ) {
        if ($appId === '' || $secret === '') {
            throw new RuntimeException('Cashfree App ID and Secret key are not set. Add them on the settings page.');
        }
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function createOrder(array $body): array
    {
        return $this->call('POST', '/orders', $body);
    }

    public function getOrder(string $orderId): array
    {
        return $this->call('GET', '/orders/' . rawurlencode($orderId), null);
    }

    public function verifyWebhook(string $rawBody, string $signature, string $timestamp): bool
    {
        if ($signature === '' || $timestamp === '') {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $timestamp . $rawBody, $this->secret, true));
        return hash_equals($expected, $signature);
    }

    private function call(string $method, string $path, ?array $body): array
    {
        $url = ($this->mode === 'production' ? 'https://api.cashfree.com/pg' : 'https://sandbox.cashfree.com/pg') . $path;
        $json = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $headers = [
            'x-client-id: ' . $this->appId,
            'x-client-secret: ' . $this->secret,
            'x-api-version: ' . $this->apiVersion,
            'Accept: application/json',
        ];
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        $t = microtime(true);
        $payload = curl_exec($ch);
        $millis = (microtime(true) - $t) * 1000;
        if ($payload === false) {
            $error = curl_error($ch);
            Log::apiCall($method, $url, 0, $millis, 0, null, $json, null, 'Cashfree: ' . $error);
            throw new CashfreeException(0, 'NETWORK_ERROR', $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        Log::apiCall($method, $url, $status, $millis, strlen((string) $payload), (string) $payload, $json, null);
        $data = json_decode((string) $payload, true);
        if (!is_array($data)) {
            throw new CashfreeException($status, 'INVALID_RESPONSE', 'Cashfree returned a body that is not JSON: ' . substr((string) $payload, 0, 200));
        }
        if ($status >= 400) {
            throw new CashfreeException($status, (string) ($data['code'] ?? $data['type'] ?? 'HTTP_' . $status), (string) ($data['message'] ?? 'Cashfree request failed'));
        }
        return $data;
    }
}
