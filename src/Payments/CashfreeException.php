<?php
declare(strict_types=1);

namespace App\Payments;

use RuntimeException;

final class CashfreeException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $errorCode, string $message)
    {
        parent::__construct($message, $status);
    }
}
