<?php
declare(strict_types=1);

namespace App\Models;

final class Attraction
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $summary,
        public ?string $promotionText,
        public ?string $heroImageUrl,
        public ?string $logoUrl,
        public ?string $city,
        public ?string $country,
        public array $categories,
        public ?float $startingPrice,
        public bool $bookable,
        public ?string $updatedAt,
        public ?string $seoTitle = null,
        public ?string $descriptionHtml = null,
        public ?string $notice = null,
        public array $highlights = [],
        public array $images = [],
        public array $locations = [],
        public array $faq = [],
        public array $goodToKnow = [],
        public ?string $contentVersion = null
    ) {
    }

    public static function fromArray(array $a): self
    {
        $images = sort_images($a['images'] ?? []);
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
            array_values($a['goodToKnow'] ?? []),
            isset($a['contentVersion']) ? (string) $a['contentVersion'] : null
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
