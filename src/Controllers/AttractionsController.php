<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Api\ApiException;
use App\Models\Attraction;
use App\Models\Offer;
use App\Models\Product;
use App\Services\Catalog;
use App\Services\Concourse;
use App\Support\Config;
use App\Support\Log;
use App\Support\Response;
use App\Support\Seo;
use App\Support\View;
use DateTimeImmutable;

final class AttractionsController
{
    private const PAGE_SIZE = 24;

    public function __construct(private Concourse $api, private Catalog $catalog)
    {
    }

    public function index(): Response
    {
        $q = input($_GET, 'q');
        $city = input($_GET, 'city');
        $category = input($_GET, 'category');
        $page = max(1, (int) ($_GET['page'] ?? 1));

        if ($q !== '') {
            $source = $this->catalog->search($q, 100);
        } else {
            $source = $this->catalog->all();
        }
        $filtered = $this->catalog->filter($source, $city, $category);
        $total = count($filtered);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);
        $items = array_slice($filtered, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

        $heading = $q !== '' ? 'Results for "' . $q . '"' : ($category !== '' ? $category : ($city !== '' ? 'Things to do in ' . $city : 'All attractions and tours'));
        $seo = Seo::current();
        $seo->title = $heading . ($page > 1 ? ' - Page ' . $page : '') . ' | ' . Config::string('site.name');
        $seo->description = $heading . ': ' . number_format($total) . ' attractions, tours and tickets with live prices and availability.';
        $seo->noindex = $q !== '';
        $seo->canonical = abs_url(query_url('/attractions', ['city' => $city, 'category' => $category, 'page' => $page > 1 ? $page : null]));
        $seo->breadcrumb([['Home', '/'], ['Attractions', '/attractions']]);
        $seo->add(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $heading, 'numberOfItems' => $total,
            'itemListElement' => array_map(static fn(Attraction $a, int $i) => ['@type' => 'ListItem', 'position' => ($page - 1) * self::PAGE_SIZE + $i + 1, 'url' => abs_url($a->path()), 'name' => $a->name], $items, array_keys($items))]);

        return Response::html(View::render('attractions/index', [
            'heading' => $heading, 'items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'q' => $q, 'city' => $city, 'category' => $category,
            'categories' => $this->catalog->categories(14), 'cities' => $this->catalog->cities(14),
        ]));
    }

    public function suggest(): Response
    {
        $q = input($_GET, 'q');
        $rows = [];
        if (mb_strlen($q) >= 3) {
            foreach ($this->catalog->search($q, 8) as $a) {
                $rows[] = ['name' => $a->name, 'place' => $a->place(), 'url' => $a->path(), 'image' => $a->heroImageUrl ?? $a->logoUrl, 'price' => $a->startingPrice];
            }
        }
        return Response::text(json_encode(['q' => $q, 'items' => $rows], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP), 'application/json');
    }

    private static function requireId(string $id): void
    {
        if (preg_match('/^[0-9a-f]{24}$/', $id) !== 1) {
            throw new \App\Support\NotFoundException('Unknown id.');
        }
    }

    public function show(string $id, string $slug): Response
    {
        self::requireId($id);
        $attraction = $this->api->attraction($id);
        $canonical = $attraction->path();
        if ($canonical !== '/attractions/' . rawurlencode($id) . '/' . $slug) {
            return Response::redirect($canonical, 301);
        }
        $products = $this->api->products($id);

        $seo = Seo::current();
        $seo->title = $attraction->seoTitle ?: $attraction->name . ' Tickets | ' . Config::string('site.name');
        $seo->description = plain_text($attraction->summary ?: $attraction->descriptionHtml, 158) ?: $this->factDescription($attraction);
        $seo->canonical = abs_url($canonical);
        $seo->image = (string) $attraction->heroImageUrl;
        $seo->breadcrumb([['Home', '/'], ['Attractions', '/attractions'], [$attraction->name, $canonical]]);
        $seo->add($this->attractionSchema($attraction, $products));
        if ($attraction->faq !== []) {
            $seo->add($this->faqSchema($attraction->faq));
        }

        return Response::html(View::render('attractions/show', ['attraction' => $attraction, 'products' => $products]));
    }

    public function product(string $id, string $slug, string $productId): Response
    {
        self::requireId($id);
        self::requireId($productId);
        $attraction = $this->api->attraction($id);
        $product = $this->api->product($productId);
        $path = $product->path($attraction);

        $month = $this->month(input($_GET, 'month'));
        $today = new DateTimeImmutable('today');
        $calendar = [];
        $priceFix = [];
        $availabilityError = null;
        if ($product->needsEvent() && $product->bookable) {
            $start = $month < $today ? $today : $month;
            $end = $month->modify('last day of this month');
            if ($start <= $end) {
                try {
                    $calendar = $this->api->availability($id, $productId, $start->format('Y-m-d'), $end->format('Y-m-d'))['calendar'] ?? [];
                } catch (ApiException $e) {
                    Log::exception($e, 'Availability failed for product ' . $productId);
                    $availabilityError = $e;
                }
            }
        } elseif ($product->bookable) {
            $missing = array_filter($product->offers, static fn(Offer $o) => $o->price === null);
            if ($missing) {
                foreach ($this->api->pricing($id, $productId)['offers'] ?? [] as $row) {
                    $priceFix[$row['offerId']] = $row['price']['amount'] ?? null;
                }
            }
        }

        $currency = null;
        foreach ($calendar as $entry) {
            $currency = $entry['offers'][0]['price']['currency'] ?? $currency;
        }
        foreach ($product->offers as $o) {
            $currency ??= $o->currency;
        }

        $seo = Seo::current();
        $seo->title = $product->name . ' - ' . $attraction->name . ' | ' . Config::string('site.name');
        $seo->description = plain_text($product->description, 158) ?: $attraction->name . ' - ' . $product->name . ': check live prices and book online.';
        $seo->canonical = abs_url($path);
        $seo->image = (string) ($product->images[0]['url'] ?? $attraction->heroImageUrl);
        $seo->breadcrumb([['Home', '/'], ['Attractions', '/attractions'], [$attraction->name, $attraction->path()], [$product->name, $path]]);
        $seo->add($this->productSchema($attraction, $product, $path));
        if ($product->faq !== []) {
            $seo->add($this->faqSchema($product->faq));
        }

        $days = [];
        foreach ($calendar as $entry) {
            $days[$entry['date']][] = $entry;
        }

        return Response::html(View::render('attractions/product', [
            'attraction' => $attraction, 'product' => $product, 'path' => $path,
            'month' => $month, 'today' => $today, 'days' => $days, 'currency' => $currency, 'priceFix' => $priceFix, 'availabilityError' => $availabilityError,
            'idempotencyKey' => bin2hex(random_bytes(16)),
        ]));
    }

    private function factDescription(Attraction $a): string
    {
        $parts = [$a->name];
        if ($a->place() !== '') {
            $parts[] = 'in ' . $a->place();
        }
        $text = implode(' ', $parts) . ': tickets and experiences';
        if ($a->startingPrice !== null) {
            $text .= ' from ' . money($a->startingPrice, 'USD');
        }
        return $text . '. Check live availability and book online.';
    }

    private function month(string $value): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m', $value);
        return $parsed !== false ? $parsed : new DateTimeImmutable('first day of this month midnight');
    }

    private function attractionSchema(Attraction $a, array $products): array
    {
        $schema = ['@context' => 'https://schema.org', '@type' => 'TouristAttraction', 'name' => $a->name, 'url' => abs_url($a->path()),
            'description' => plain_text($a->summary ?: $a->descriptionHtml, 300)];
        if ($a->heroImageUrl) {
            $schema['image'] = $a->heroImageUrl;
        }
        $loc = $a->locations[0] ?? null;
        if ($loc) {
            $schema['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $loc['streetAddress'] ?? null, 'addressLocality' => $loc['city'] ?? null,
                'addressRegion' => $loc['state'] ?? null, 'postalCode' => $loc['zip'] ?? null, 'addressCountry' => $a->country]);
            if (isset($loc['latitude'], $loc['longitude'])) {
                $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $loc['latitude'], 'longitude' => $loc['longitude']];
            }
        }
        if ($a->startingPrice !== null) {
            $schema['priceRange'] = 'From ' . money($a->startingPrice, 'USD');
        }
        return $schema;
    }

    private function productSchema(Attraction $a, Product $p, string $path): array
    {
        $prices = $p->fromPrice();
        $schema = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p->name . ' - ' . $a->name, 'url' => abs_url($path),
            'description' => plain_text($p->description, 300)];
        $image = $p->images[0]['url'] ?? $a->heroImageUrl;
        if ($image) {
            $schema['image'] = $image;
        }
        if ($prices !== null) {
            $schema['offers'] = ['@type' => 'Offer', 'price' => number_format($prices, 2, '.', ''), 'priceCurrency' => $p->offers[0]->currency ?? 'USD',
                'url' => abs_url($path), 'availability' => $p->bookable ? 'https://schema.org/InStock' : 'https://schema.org/Discontinued'];
        }
        return $schema;
    }

    private function faqSchema(array $faq): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(
            static fn(array $f) => ['@type' => 'Question', 'name' => plain_text($f['q'] ?? ''), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => plain_text($f['a'] ?? '')]],
            $faq
        )];
    }
}
