<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function input(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function money(?float $amount, ?string $currency): string
{
    if ($amount === null) {
        return '';
    }
    $symbol = $currency === 'USD' ? '$' : ($currency === null ? '' : $currency . ' ');
    return $symbol . number_format($amount, 2);
}

function slugify(string $text): string
{
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text) ?? '', '-'));
    return $text;
}

function base_url(): string
{
    $configured = App\Support\Config::string('site.public_url');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function abs_url(string $path): string
{
    return base_url() . $path;
}

function query_url(string $path, array $params): string
{
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '' && $v !== 1);
    return $path . ($params ? '?' . http_build_query($params) : '');
}

function plain_text(?string $html, int $max = 0): string
{
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    if ($max > 0 && mb_strlen($text) > $max) {
        $text = rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }
    return $text;
}
