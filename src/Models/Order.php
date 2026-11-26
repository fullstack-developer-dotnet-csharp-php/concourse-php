<?php
declare(strict_types=1);

namespace App\Models;

final class Order
{
    public function __construct(
        public string $orderNumber,
        public string $status,
        public ?string $partnerOrderRef,
        public string $attractionId,
        public string $productId,
        public int $quantity,
        public ?float $priceTotal,
        public ?string $currency,
        public string $guestName,
        public ?string $guestEmail,
        public ?string $eventDate,
        public ?string $orderDate,
        public array $lines,
        public array $tickets,
        public array $vouchers,
        public ?array $cancellation,
        public ?string $env,
        public array $items = []
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
            $a['env'] ?? null,
            $a['items'] ?? []
        );
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'refunded', 'refund_requested'], true);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['confirmed', 'partial'], true);
    }
}
