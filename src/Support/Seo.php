<?php
declare(strict_types=1);

namespace App\Support;

final class Seo
{
    public string $title = '';
    public string $description = '';
    public string $canonical = '';
    public string $image = '';
    public string $type = 'website';
    public bool $noindex = false;
    public array $jsonLd = [];
    public array $breadcrumbs = [];

    private static ?self $instance = null;

    public static function current(): self
    {
        return self::$instance ??= new self();
    }

    public function add(array $schema): void
    {
        $this->jsonLd[] = $schema;
    }

    public function breadcrumb(array $crumbs): void
    {
        $this->breadcrumbs = $crumbs;
        $items = [];
        foreach ($crumbs as $i => [$name, $path]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => abs_url($path)];
        }
        $this->add(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]);
    }

    public function renderJsonLd(): string
    {
        $out = '';
        foreach ($this->jsonLd as $schema) {
            $out .= '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n";
        }
        return $out;
    }
}
