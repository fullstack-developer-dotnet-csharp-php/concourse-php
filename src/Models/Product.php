<?php
declare(strict_types=1);

namespace App\Models;

final class Product
{
    public function __construct(
        public string $id,
        public string $attractionId,
        public string $name,
        public ?string $description,
        public bool $bookable,
        public ?int $durationMinutes,
        public array $details,
        public array $images,
        public array $offers,
        public array $faq,
        public ?string $contentVersion = null
    ) {
    }

    public static function fromArray(array $a): self
    {
        return new self(
            (string) $a['id'],
            (string) ($a['attractionId'] ?? ''),
            (string) ($a['name'] ?? ''),
            self::readDescription($a['description'] ?? null),
            (bool) ($a['bookable'] ?? false),
            isset($a['durationMinutes']) && (int) $a['durationMinutes'] > 0 ? (int) $a['durationMinutes'] : null,
            $a['details'] ?? [],
            sort_images($a['images'] ?? []),
            array_map(static fn(array $o) => Offer::fromArray($o), $a['offers'] ?? []),
            $a['faq'] ?? [],
            isset($a['contentVersion']) ? (string) $a['contentVersion'] : null
        );
    }

    private static function readDescription(?string $raw): ?string
    {
        if ($raw === null || !str_starts_with(ltrim($raw), '{')) {
            return $raw;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $raw;
        }
        $h = static fn($v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $para = static fn(string $title, $v): string => is_string($v) && trim($v) !== '' ? '<h3>' . $title . '</h3><p>' . nl2br($h($v)) . '</p>' : '';
        $list = static function (string $title, $v) use ($h): string {
            if (!is_array($v) || $v === []) {
                return '';
            }
            $items = '';
            foreach ($v as $item) {
                $item = ltrim(trim((string) $item), "- ");
                if ($item !== '') {
                    $items .= '<li>' . $h($item) . '</li>';
                }
            }
            return $items !== '' ? '<h3>' . $title . '</h3><ul>' . $items . '</ul>' : '';
        };
        $out = '';
        if (is_string($data['Description'] ?? null) && trim($data['Description']) !== '') {
            $out .= '<p>' . nl2br($h($data['Description'])) . '</p>';
        }
        $out .= $list('Inclusions', $data['Inclusions'] ?? null);
        $out .= $list('Good to know', array_values(array_filter((array) ($data['AdditionalInfo'] ?? []), static fn($x) => trim((string) $x) !== '' && !str_ends_with(trim((string) $x), ':'))));
        $out .= $para('Cancellation policy', $data['CancellationPolicy'] ?? null);
        $out .= $para('Voucher', $data['VoucherType'] ?? null);
        $out .= $para('Meeting location', $data['MeetingLocation'] ?? null);
        $out .= $para('Redemption instructions', $data['RedemptionInstructions'] ?? null);
        return $out !== '' ? $out : null;
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
