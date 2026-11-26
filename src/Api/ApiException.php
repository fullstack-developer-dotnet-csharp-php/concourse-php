<?php
declare(strict_types=1);

namespace App\Api;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        public int $status,
        public string $errorCode,
        string $message,
        public string $url,
        public string $body = '',
        public ?int $retryAfter = null
    ) {
        parent::__construct($message, $status);
    }
}
