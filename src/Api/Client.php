<?php
declare(strict_types=1);

namespace App\Api;

use App\Support\FileCache;
use App\Support\Log;

final class Client
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private int $timeout,
        private FileCache $cache
    ) {
    }

    public function get(string $path, array $query = [], int $ttl = 0): array
    {
        $url = $this->url($path, $query);
        $cached = $this->cache->get($url, $ttl);
        if ($cached !== null) {
            Log::cacheEvent('hit', $url, ['ttl' => $ttl]);
            return $cached;
        }
        if ($ttl > 0) {
            Log::cacheEvent('miss', $url, ['ttl' => $ttl]);
        }
        $result = $this->request('GET', $url, null, null);
        $data = $this->decode($result, $url);
        if ($ttl > 0) {
            $this->cache->put($url, $data);
        }
        return $data;
    }

    public function post(string $path, array $body, ?string $idempotencyKey = null): array
    {
        $url = $this->url($path, []);
        $result = $this->request('POST', $url, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $idempotencyKey);
        return $this->decode($result, $url);
    }

    public function raw(string $path, array $query = []): array
    {
        $url = $this->url($path, $query);
        $result = $this->request('GET', $url, null, null, ($query['format'] ?? '') === 'pdf' ? 'application/pdf' : 'text/html');
        if ($result['status'] >= 400) {
            $this->decode($result, $url);
        }
        return $result;
    }

    private function url(string $path, array $query): string
    {
        $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
        return rtrim($this->baseUrl, '/') . $path . ($query ? '?' . http_build_query($query) : '');
    }

    private function request(string $method, string $url, ?string $body, ?string $idempotencyKey, string $accept = 'application/json, application/pdf, text/html'): array
    {
        $headers = ['Authorization: Bearer ' . $this->apiKey, 'Accept: ' . $accept];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $t = microtime(true);
        $payload = curl_exec($ch);
        if ($payload === false && ($method === 'GET' || $idempotencyKey !== null)) {
            Log::warning('API network error, retrying once', ['method' => $method, 'url' => $url, 'error' => curl_error($ch)]);
            usleep(400000);
            $payload = curl_exec($ch);
        }
        $millis = (microtime(true) - $t) * 1000;
        if ($payload === false) {
            $error = curl_error($ch);
            Log::apiCall($method, $url, 0, $millis, 0, null, $body, null, curl_errno($ch) . ' ' . $error);
            throw new ApiException(0, 'NETWORK_ERROR', $error, $url);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $retryAfter = isset($responseHeaders['retry-after']) ? (int) $responseHeaders['retry-after'] : null;
        Log::apiCall($method, $url, $status, $millis, strlen((string) $payload), (string) $payload, $body, $retryAfter);
        return [
            'status' => $status,
            'body' => (string) $payload,
            'contentType' => $responseHeaders['content-type'] ?? '',
            'retryAfter' => isset($responseHeaders['retry-after']) ? (int) $responseHeaders['retry-after'] : null,
        ];
    }

    private function decode(array $result, string $url): array
    {
        $decoded = json_decode($result['body'], true);
        if ($result['status'] >= 400 || $result['status'] === 0) {
            $code = is_array($decoded) ? (string) ($decoded['error']['code'] ?? 'HTTP_' . $result['status']) : 'HTTP_' . $result['status'];
            $message = is_array($decoded) ? (string) ($decoded['error']['message'] ?? 'Request failed') : trim($result['body']);
            throw new ApiException($result['status'], $code, $message, $url, $result['body'], $result['retryAfter']);
        }
        if (!is_array($decoded)) {
            throw new ApiException($result['status'], 'INVALID_JSON', 'The API returned a body that is not JSON.', $url, $result['body']);
        }
        return $decoded;
    }
}
