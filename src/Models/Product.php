<?php
declare(strict_types=1);

namespace App\Models;

final class Product
{
    public function __construct(
        public readonly string $id,
        public readonly string $attractionId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $bookable,
        public readonly ?int $durationMinutes,
        public readonly array $details,
        public readonly array $images,
        public readonly array $offers,
        public readonly array $faq
    ) {
    }

    public static function fromArray(array $a): self
    {
        return new self(
            (string) $a['id'],
            (string) ($a['attractionId'] ?? ''),
            (string) ($a['name'] ?? ''),
            $a['description'] ?? null,
            (bool) ($a['bookable'] ?? false),
            isset($a['durationMinutes']) && (int) $a['durationMinutes'] > 0 ? (int) $a['durationMinutes'] : null,
            $a['details'] ?? [],
            $a['images'] ?? [],
            array_map(static fn(array $o) => Offer::fromArray($o), $a['offers'] ?? []),
            $a['faq'] ?? []
        );
    }

    public function needsEvent(): bool
    {
        foreach ($this->offers as $offer) {
            if ($offer->needsEvent !== 'none') {
                return true;
            }
        }
        return false;
    }

    public function path(Attraction $attraction): string
    {
        return $attraction->path() . '/tickets/' . rawurlencode($this->id);
    }

    public function fromPrice(): ?float
    {
        $prices = array_filter(array_map(static fn(Offer $o) => $o->price, $this->offers), static fn($p) => $p !== null);
        return $prices ? min($prices) : null;
    }

    public function cancellable(): ?bool
    {
        $values = array_filter(array_map(static fn(Offer $o) => $o->cancellable, $this->offers), static fn($v) => $v !== null);
        if (!$values) {
            return null;
        }
        return !in_array(false, $values, true);
    }
}
