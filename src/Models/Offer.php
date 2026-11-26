<?php
declare(strict_types=1);

namespace App\Models;

final class Offer
{
    public function __construct(
        public string $offerId,
        public string $label,
        public ?string $categoryLabel,
        public ?int $ageMin,
        public ?int $ageMax,
        public string $pricingMode,
        public bool $bookable,
        public ?float $price,
        public ?string $currency,
        public int $minQty,
        public ?int $maxQty,
        public string $needsEvent,
        public array $travellerFields,
        public array $questions,
        public ?bool $cancellable,
        public ?string $cancellationTerms,
        public ?int $cutoffMinutes,
        public ?string $validFrom = null,
        public ?string $validTo = null
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
            isset($cancel['cutoffMinutes']) ? (int) $cancel['cutoffMinutes'] : null,
            $a['constraints']['validFrom'] ?? null,
            $a['constraints']['validTo'] ?? null
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
