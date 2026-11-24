<?php
declare(strict_types=1);

namespace App\Models;

final class Attraction
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $summary,
        public readonly ?string $promotionText,
        public readonly ?string $heroImageUrl,
        public readonly ?string $logoUrl,
        public readonly ?string $city,
        public readonly ?string $country,
        public readonly array $categories,
        public readonly ?float $startingPrice,
        public readonly bool $bookable,
        public readonly ?string $updatedAt,
        public readonly ?string $seoTitle = null,
        public readonly ?string $descriptionHtml = null,
        public readonly ?string $notice = null,
        public readonly array $highlights = [],
        public readonly array $images = [],
        public readonly array $locations = [],
        public readonly array $faq = [],
        public readonly array $goodToKnow = []
    ) {
    }

    public static function fromArray(array $a): self
    {
        $images = $a['images'] ?? [];
        $hero = $a['heroImageUrl'] ?? ($images[0]['url'] ?? null);
        return new self(
            (string) $a['id'],
            (string) ($a['name'] ?? ''),
            (string) ($a['slug'] ?? ''),
            $a['summary'] ?? null,
            ($a['promotionText'] ?? '') !== '' ? $a['promotionText'] : null,
            $hero,
            $a['logoUrl'] ?? null,
            $a['city'] ?? null,
            $a['country'] ?? null,
            array_values($a['categories'] ?? []),
            isset($a['startingPrice']) ? (float) $a['startingPrice'] : null,
            (bool) ($a['bookable'] ?? false),
            $a['updatedAt'] ?? null,
            $a['seoTitle'] ?? null,
            $a['descriptionHtml'] ?? null,
            ($a['notice'] ?? '') !== '' ? $a['notice'] : null,
            array_values($a['highlights'] ?? []),
            $images,
            $a['locations'] ?? [],
            $a['faq'] ?? [],
            array_values($a['goodToKnow'] ?? [])
        );
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'promotionText' => $this->promotionText,
            'heroImageUrl' => $this->heroImageUrl,
            'logoUrl' => $this->logoUrl,
            'city' => $this->city,
            'country' => $this->country,
            'categories' => $this->categories,
            'startingPrice' => $this->startingPrice,
            'bookable' => $this->bookable,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public function path(): string
    {
        return '/attractions/' . rawurlencode($this->id) . '/' . ($this->slug !== '' ? $this->slug : slugify($this->name));
    }

    public function place(): string
    {
        return implode(', ', array_filter([$this->city, $this->country]));
    }
}
