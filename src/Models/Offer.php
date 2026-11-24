<?php
declare(strict_types=1);

namespace App\Models;

final class Offer
{
    public function __construct(
        public readonly string $offerId,
        public readonly string $label,
        public readonly ?string $categoryLabel,
        public readonly ?int $ageMin,
        public readonly ?int $ageMax,
        public readonly string $pricingMode,
        public readonly bool $bookable,
        public readonly ?float $price,
        public readonly ?string $currency,
        public readonly int $minQty,
        public readonly ?int $maxQty,
        public readonly string $needsEvent,
        public readonly array $travellerFields,
        public readonly array $questions,
        public readonly ?bool $cancellable,
        public readonly ?string $cancellationTerms,
        public readonly ?int $cutoffMinutes
    ) {
    }

    public static function fromArray(array $a): self
    {
        $contract = $a['bookingContract'] ?? [];
        $cancel = $contract['cancellation'] ?? [];
        return new self(
            (string) $a['offerId'],
            (string) ($a['label'] ?? ''),
            $a['category']['label'] ?? null,
            $a['ageRange']['min'] ?? null,
            $a['ageRange']['max'] ?? null,
            (string) ($a['pricingMode'] ?? ''),
            (bool) ($a['bookable'] ?? false),
            isset($a['price']['amount']) ? (float) $a['price']['amount'] : null,
            $a['price']['currency'] ?? null,
            (int) ($a['constraints']['minQty'] ?? 1),
            isset($a['constraints']['maxQty']) ? (int) $a['constraints']['maxQty'] : null,
            (string) ($contract['needsEvent'] ?? 'none'),
            $contract['travellerFields'] ?? [],
            $contract['questions'] ?? [],
            isset($cancel['cancellable']) ? (bool) $cancel['cancellable'] : null,
            $cancel['terms'] ?? null,
            isset($cancel['cutoffMinutes']) ? (int) $cancel['cutoffMinutes'] : null
        );
    }

    public function ageLabel(): ?string
    {
        if ($this->ageMin === null && $this->ageMax === null) {
            return null;
        }
        if ($this->ageMax === null) {
            return $this->ageMin . '+ yrs';
        }
        return ($this->ageMin ?? 0) . '-' . $this->ageMax . ' yrs';
    }
}
