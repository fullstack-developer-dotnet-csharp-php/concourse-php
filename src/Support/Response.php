<?php
declare(strict_types=1);

namespace App\Support;

final class Response
{
    public function __construct(
        public int $status,
        public string $body,
        public array $headers = []
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $body, string $type, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => $type . '; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self($status, '', ['Location' => $url]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
