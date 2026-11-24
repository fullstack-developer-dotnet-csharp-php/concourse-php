<?php
declare(strict_types=1);

namespace App\Payments;

use App\Services\Checkout;
use App\Services\Concourse;
use App\Support\Config;

final class Gateway
{
    private ?Cashfree $cashfree = null;
    private ?PendingStore $pending = null;
    private ?Checkout $checkout = null;

    public function __construct(private Concourse $api)
    {
    }

    public function enabled(): bool
    {
        return Config::bool('payments.enabled');
    }

    public function cashfree(): Cashfree
    {
        return $this->cashfree ??= new Cashfree(
            Config::string('payments.cashfree.mode'),
            Config::string('payments.cashfree.app_id'),
            Config::string('payments.cashfree.secret_key'),
            Config::string('payments.cashfree.api_version')
        );
    }

    public function pending(): PendingStore
    {
        return $this->pending ??= new PendingStore(__DIR__ . '/../../storage/payments');
    }

    public function checkout(): Checkout
    {
        return $this->checkout ??= new Checkout($this->api, $this->cashfree(), $this->pending());
    }
}
