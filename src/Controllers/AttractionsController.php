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

        $near = input($_GET, 'near');
        if ($near !== '' && preg_match('/^-?\d{1,3}(\.\d+)?,-?\d{1,3}(\.\d+)?(,\d{1,3})?$/', $near) !== 1) {
            $near = '';
        }
        if ($near !== '') {
            try {
                $source = array_values(array_filter($this->api->attractionsPage(1, 100, $q !== '' ? $q : null, false, $near)['items'], static fn(Attraction $a) => $a->bookable));
            } catch (ApiException $e) {
                Log::exception($e, 'Near search failed');
                $source = [];
            }
        } elseif ($q !== '') {
            $source = $this->catalog->search($q, 100);
        } else {
            $source = $this->catalog->all();
        }
        $filtered = $this->catalog->filter($source, $city, $category);
        $total = count($filtered);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);
        $items = array_slice($filtered, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

        $heading = $near !== '' ? 'Attractions near you' : ($q !== '' ? 'Results for "' . $q . '"' : ($category !== '' ? $category : ($city !== '' ? 'Things to do in ' . $city : 'All attractions and tours')));
        $seo = Seo::current();
        $seo->title = $heading . ($page > 1 ? ' - Page ' . $page : '') . ' | ' . Config::string('site.name');
        $seo->description = $heading . ': ' . number_format($total) . ' attractions, tours and tickets with live prices and availability.';
        $seo->noindex = $q !== '' || $near !== '';
        $seo->canonical = abs_url(query_url('/attractions', ['city' => $city, 'category' => $category, 'page' => $page > 1 ? $page : null]));
        $seo->breadcrumb([['Home', '/'], ['Attractions', '/attractions']]);
        $seo->add(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $heading, 'numberOfItems' => $total,
            'itemListElement' => array_map(static fn(Attraction $a, int $i) => ['@type' => 'ListItem', 'position' => ($page - 1) * self::PAGE_SIZE + $i + 1, 'url' => abs_url($a->path()), 'name' => $a->name], $items, array_keys($items))]);

        return Response::html(View::render('attractions/index', [
            'heading' => $heading, 'items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'q' => $q, 'city' => $city, 'category' => $category, 'near' => $near,
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

    public function questionValues(string $id, string $productId, string $key): Response
    {
        self::requireId($id);
        self::requireId($productId);
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $key) !== 1) {
            throw new \App\Support\NotFoundException('Unknown question.');
        }
        $answers = [];
        foreach ((array) ($_GET['answer'] ?? []) as $k => $v) {
            if (preg_match('/^[A-Za-z0-9_]{1,64}$/', (string) $k) === 1 && is_scalar($v)) {
                $answers[(string) $k] = mb_substr((string) $v, 0, 200);
            }
        }
        try {
            $data = $this->api->questionValues($id, $productId, $key, mb_substr(input($_GET, 'q'), 0, 100), 20, $answers);
            $items = array_map(static fn(array $i) => ['key' => (string) ($i['key'] ?? ''), 'label' => (string) ($i['label'] ?? ''), 'detail' => (string) ($i['detail'] ?? '')], $data['items'] ?? []);
            $body = ['key' => (string) ($data['key'] ?? $key), 'allowFreeText' => (bool) ($data['allowFreeText'] ?? false), 'items' => array_values($items)];
            $status = 200;
        } catch (ApiException $e) {
            Log::exception($e, 'Question values failed');
            $body = ['error' => 'The list of choices could not be loaded right now.'];
            $status = 502;
        }
        return Response::text(json_encode($body, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP), 'application/json', $status);
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

        $bookable = array_values(array_filter($products, static fn(Product $p) => $p->bookable));
        $data = ['attraction' => $attraction, 'products' => $products];
        if (count($bookable) === 1) {
            $product = $bookable[0];
            $data += ['inline' => $product, 'product' => $product, 'path' => $canonical, 'anchor' => '#tickets'] + $this->bookingState($attraction, $product, $canonical);
        }
        return Response::html(View::render('attractions/show', $data));
    }

    public function product(string $id, string $slug, string $productId): Response
    {
        self::requireId($id);
        self::requireId($productId);
        $attraction = $this->api->attraction($id);
        $product = $this->api->product($productId);
        $path = $product->path($attraction);

        $state = $this->bookingState($attraction, $product, $path);
        $month = $state['month'];
        $today = $state['today'];
        $calendar = $state['calendar'];
        $currency = $state['currency'];

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

        return Response::html(View::render('attractions/product', ['attraction' => $attraction, 'product' => $product, 'path' => $path] + $state));
    }

    private function bookingState(Attraction $attraction, Product $product, string $path): array
    {
        $id = $attraction->id;
        $productId = $product->id;
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

        $nextDate = null;
        $noAvailability = false;
        $horizon = $today->modify('+12 months');
        if ($product->needsEvent() && $product->bookable && $availabilityError === null && $calendar === []) {
            $from = max($month, $today);
            $windowStart = $from;
            $scanned = $from;
            try {
                while ($windowStart <= $horizon) {
                    $windowEnd = $windowStart->modify('+29 days');
                    $rows = $this->api->availability($id, $productId, $windowStart->format('Y-m-d'), $windowEnd->format('Y-m-d'))['calendar'] ?? [];
                    $scanned = $windowEnd;
                    if ($rows !== []) {
                        $dates = array_map(static fn(array $r) => (string) $r['date'], $rows);
                        sort($dates);
                        $nextDate = $dates[0];
                        break;
                    }
                    $windowStart = $windowEnd->modify('+1 day');
                }
            } catch (ApiException $e) {
                Log::exception($e, 'Availability lookahead failed for product ' . $productId);
                $availabilityError = $e;
            }
            $noAvailability = $nextDate === null && $availabilityError === null;
            $horizon = $scanned;
        }

        $currency = null;
        foreach ($calendar as $entry) {
            $currency = $entry['offers'][0]['price']['currency'] ?? $currency;
        }
        foreach ($product->offers as $o) {
            $currency ??= $o->currency;
        }

        $days = [];
        foreach ($calendar as $entry) {
            $days[$entry['date']][] = $entry;
        }

        return ['month' => $month, 'today' => $today, 'calendar' => $calendar, 'days' => $days, 'currency' => $currency, 'priceFix' => $priceFix,
            'availabilityError' => $availabilityError, 'nextDate' => $nextDate, 'noAvailability' => $noAvailability, 'horizon' => $horizon, 'idempotencyKey' => bin2hex(random_bytes(16))];
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
