<?php
declare(strict_types=1);

namespace App\Models;

final class Order
{
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $status,
        public readonly ?string $partnerOrderRef,
        public readonly string $attractionId,
        public readonly string $productId,
        public readonly int $quantity,
        public readonly ?float $priceTotal,
        public readonly ?string $currency,
        public readonly string $guestName,
        public readonly ?string $guestEmail,
        public readonly ?string $eventDate,
        public readonly ?string $orderDate,
        public readonly array $lines,
        public readonly array $tickets,
        public readonly array $vouchers,
        public readonly ?array $cancellation,
        public readonly ?string $env
    ) {
    }

    public static function fromArray(array $a): self
    {
        $guest = $a['guest'] ?? [];
        return new self(
            (string) $a['orderNumber'],
            (string) ($a['status'] ?? ''),
            $a['partnerOrderRef'] ?? null,
            (string) ($a['attractionId'] ?? ''),
            (string) ($a['productId'] ?? ''),
            (int) ($a['quantity'] ?? 0),
            isset($a['priceTotal']) ? (float) $a['priceTotal'] : null,
            $a['currency'] ?? null,
            trim(($guest['first'] ?? '') . ' ' . ($guest['last'] ?? '')),
            $guest['email'] ?? null,
            $a['eventDate'] ?? null,
            $a['orderDate'] ?? null,
            $a['lines'] ?? [],
            $a['tickets'] ?? [],
            $a['vouchers'] ?? [],
            $a['cancellation'] ?? null,
            $a['env'] ?? null
        );
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'refunded'], true);
    }
}
