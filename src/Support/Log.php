<?php
declare(strict_types=1);

namespace App\Support;

use Throwable;

final class Log
{
    private const LEVELS = ['debug' => 100, 'info' => 200, 'notice' => 250, 'warning' => 300, 'error' => 400, 'critical' => 500];
    private const SENSITIVE = '/authorization|api[-_]?key|token|password|passwd|secret|cookie|idempotency|card|cvv|iban|email|phone|dob|birth|firstname|lastname|first_name|last_name|guest|traveller|answers/i';

    private static string $dir = '';
    private static int $min = 200;
    private static int $retentionDays = 14;
    private static int $maxField = 2000;
    private static string $requestId = '';
    private static float $started = 0.0;
    private static int $apiCalls = 0;
    private static float $apiMillis = 0.0;
    private static array $counts = [];
    private static bool $summaryWritten = false;

    public static function init(string $dir, string $level, int $retentionDays, int $maxField): void
    {
        self::$dir = rtrim($dir, '/\\');
        self::$min = self::LEVELS[strtolower($level)] ?? self::LEVELS['info'];
        self::$retentionDays = $retentionDays;
        self::$maxField = $maxField;
        self::$started = microtime(true);
        self::$requestId = bin2hex(random_bytes(4));
        if (!is_dir(self::$dir) && !mkdir(self::$dir, 0775, true) && !is_dir(self::$dir)) {
            throw new \RuntimeException('Log directory cannot be created: ' . self::$dir);
        }
    }

    public static function register(): void
    {
        set_error_handler(static function (int $no, string $message, string $file, int $line): bool {
            $level = in_array($no, [E_USER_ERROR, E_RECOVERABLE_ERROR], true) ? 'error' : (in_array($no, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true) ? 'notice' : 'warning');
            self::write($level, 'PHP ' . self::errorName($no) . ': ' . $message, ['file' => self::relative($file), 'line' => $line]);
            return false;
        });
        set_exception_handler(static function (Throwable $e): void {
            self::exception($e, 'Uncaught exception');
            http_response_code(500);
            echo 'Internal Server Error. Request ' . self::$requestId . ' was logged.';
        });
        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::write('critical', 'Fatal ' . self::errorName($err['type']) . ': ' . $err['message'], ['file' => self::relative($err['file']), 'line' => $err['line']]);
            }
            self::summary();
        });
    }

    public static function requestId(): string
    {
        return self::$requestId;
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::write('notice', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::write('critical', $message, $context);
    }

    public static function exception(Throwable $e, string $message = 'Exception', array $context = []): void
    {
        $chain = [];
        for ($x = $e; $x !== null; $x = $x->getPrevious()) {
            $chain[] = [
                'class' => $x::class,
                'code' => $x->getCode(),
                'message' => $x->getMessage(),
                'at' => self::relative($x->getFile()) . ':' . $x->getLine(),
            ];
        }
        $context['exception'] = $chain;
        if ($e instanceof \App\Api\ApiException) {
            $context['api'] = ['status' => $e->status, 'code' => $e->errorCode, 'url' => $e->url, 'retryAfter' => $e->retryAfter, 'body' => $e->body];
        }
        $context['trace'] = array_map([self::class, 'relative'], explode("\n", $e->getTraceAsString()));
        self::write('error', $message . ': ' . $e->getMessage(), $context);
    }

    public static function apiCall(string $method, string $url, int $status, float $millis, int $bytes, ?string $body, ?string $requestBody, ?int $retryAfter, ?string $curlError = null): void
    {
        self::$apiCalls++;
        self::$apiMillis += $millis;
        $level = $status === 0 ? 'error' : ($status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info'));
        $context = ['method' => $method, 'url' => $url, 'status' => $status, 'ms' => round($millis, 1), 'bytes' => $bytes];
        if ($retryAfter !== null) {
            $context['retryAfter'] = $retryAfter;
        }
        if ($curlError !== null) {
            $context['curlError'] = $curlError;
        }
        if ($body !== null) {
            $decodedBody = json_decode($body, true);
            $context['responseBody'] = is_array($decodedBody) ? $decodedBody : $body;
        }
        if ($requestBody !== null) {
            $decoded = json_decode($requestBody, true);
            $context['requestBody'] = is_array($decoded) ? $decoded : $requestBody;
        }
        self::write($level, 'API ' . $method . ' ' . self::path($url) . ' -> ' . ($status === 0 ? 'no response' : $status), $context);
    }

    public static function cacheEvent(string $event, string $key, array $context = []): void
    {
        self::write('debug', 'Cache ' . $event, ['key' => $key] + $context);
    }

    public static function time(string $label, callable $work): mixed
    {
        $t = microtime(true);
        try {
            return $work();
        } finally {
            self::write('debug', 'Timing ' . $label, ['ms' => round((microtime(true) - $t) * 1000, 1)]);
        }
    }

    public static function summary(): void
    {
        if (self::$summaryWritten || self::$dir === '') {
            return;
        }
        self::$summaryWritten = true;
        $status = http_response_code();
        $level = $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info');
        self::write($level, 'Request finished ' . ($status ?: 200), [
            'status' => $status ?: 200,
            'ms' => round((microtime(true) - self::$started) * 1000, 1),
            'apiCalls' => self::$apiCalls,
            'apiMs' => round(self::$apiMillis, 1),
            'peakMemoryMb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'logged' => self::$counts,
        ]);
    }

    public static function requestStart(): void
    {
        self::write('info', 'Request started ' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . ($_SERVER['REQUEST_URI'] ?? ''), [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'query' => self::redact($_GET),
            'form' => self::redact($_POST),
        ]);
    }

    public static function redact(mixed $value, string $key = ''): mixed
    {
        if ($key !== '' && preg_match(self::SENSITIVE, $key) === 1) {
            return is_scalar($value) || $value === null ? self::mask((string) $value) : '[redacted]';
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::redact($v, (string) $k);
            }
            return $out;
        }
        if (is_string($value)) {
            $value = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/', 'Bearer [redacted]', $value) ?? $value;
            $value = preg_replace('/fdp_(stg|live|test)_[A-Za-z0-9]+/', 'fdp_$1_[redacted]', $value) ?? $value;
            return mb_strlen($value) > self::$maxField ? mb_substr($value, 0, self::$maxField) . '...[truncated ' . (mb_strlen($value) - self::$maxField) . ' chars]' : $value;
        }
        return $value;
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        $rank = self::LEVELS[$level] ?? self::LEVELS['info'];
        if (self::$dir === '' || $rank < self::$min) {
            return;
        }
        self::$counts[$level] = (self::$counts[$level] ?? 0) + 1;
        $now = microtime(true);
        $stamp = gmdate('Y-m-d\TH:i:s', (int) $now) . sprintf('.%03dZ', ($now - floor($now)) * 1000);
        $json = $context === [] ? '' : ' ' . json_encode(self::redact($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $line = sprintf("%s [%s] [%s] %s%s\n", $stamp, strtoupper($level), self::$requestId, str_replace(["\r", "\n"], ' ', $message), $json);
        $file = self::$dir . DIRECTORY_SEPARATOR . 'app-' . gmdate('Y-m-d') . '.log';
        $isNew = !is_file($file);
        if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('Log write failed for ' . $file . ': ' . $line);
            return;
        }
        if ($isNew) {
            self::prune();
        }
    }

    private static function prune(): void
    {
        $limit = time() - self::$retentionDays * 86400;
        foreach (glob(self::$dir . DIRECTORY_SEPARATOR . 'app-*.log') ?: [] as $f) {
            if (filemtime($f) < $limit && !unlink($f)) {
                error_log('Log prune failed for ' . $f);
            }
        }
    }

    private static function mask(string $v): string
    {
        $len = mb_strlen($v);
        return $len === 0 ? '' : ($len <= 4 ? '****' : mb_substr($v, 0, 2) . '***' . '(' . $len . ')');
    }

    private static function path(string $url): string
    {
        $p = parse_url($url);
        return ($p['path'] ?? '') . (isset($p['query']) ? '?' . $p['query'] : '');
    }

    private static function relative(string $text): string
    {
        $root = realpath(__DIR__ . '/../..') ?: '';
        return $root === '' ? $text : str_replace([$root . DIRECTORY_SEPARATOR, $root . '/'], '', $text);
    }

    private static function errorName(int $no): string
    {
        return match ($no) {
            E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'Warning',
            E_NOTICE, E_USER_NOTICE => 'Notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
            E_PARSE => 'Parse',
            E_RECOVERABLE_ERROR => 'Recoverable',
            default => 'Error',
        };
    }
}
