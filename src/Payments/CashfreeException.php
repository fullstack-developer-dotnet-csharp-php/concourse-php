<?php
declare(strict_types=1);

namespace App\Payments;

use RuntimeException;

final class CashfreeException extends RuntimeException
{
    public function __construct(public int $status, public string $errorCode, string $message)
    {
        parent::__construct($message, $status);
    }
}
