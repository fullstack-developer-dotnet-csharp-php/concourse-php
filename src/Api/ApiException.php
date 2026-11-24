<?php
declare(strict_types=1);

namespace App\Api;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly string $url,
        public readonly string $body = '',
        public readonly ?int $retryAfter = null
    ) {
        parent::__construct($message, $status);
    }
}
