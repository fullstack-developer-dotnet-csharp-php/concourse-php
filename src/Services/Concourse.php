<?php
declare(strict_types=1);

namespace App\Services;

use App\Api\Client;
use App\Models\Attraction;
use App\Models\Order;
use App\Models\Product;
use App\Support\Config;

final class Concourse
{
    public function __construct(private Client $client)
    {
    }

    public function attractionsPage(int $page, int $pageSize, ?string $q = null, bool $fresh = false, ?string $near = null): array
    {
        $data = $this->client->get('/attractions', ['page' => $page, 'pageSize' => $pageSize, 'q' => $q, 'near' => $near], $fresh ? 0 : Config::int('cache.ttl.catalog'));
        return [
            'items' => array_map(static fn(array $a) => Attraction::fromArray($a), $data['items'] ?? []),
            'total' => (int) ($data['total'] ?? 0),
            'page' => (int) ($data['page'] ?? $page),
            'pageSize' => (int) ($data['pageSize'] ?? $pageSize),
        ];
    }

    public function attraction(string $id, bool $fresh = false): Attraction
    {
        return Attraction::fromArray($this->client->get('/attractions/' . rawurlencode($id), [], $fresh ? 0 : Config::int('cache.ttl.detail')));
    }

    public function events(?int $after, int $limit): array
    {
        $data = $this->client->get('/events', ['after' => $after, 'limit' => $limit], 0);
        return ['events' => $data['events'] ?? [], 'nextAfter' => (int) ($data['nextAfter'] ?? ($after ?? 0)), 'hasMore' => (bool) ($data['hasMore'] ?? false)];
    }

    public function products(string $attractionId): array
    {
        $data = $this->client->get('/attractions/' . rawurlencode($attractionId) . '/products', [], Config::int('cache.ttl.detail'));
        return array_map(static fn(array $p) => Product::fromArray($p), $data);
    }

    public function product(string $productId): Product
    {
        return Product::fromArray($this->client->get('/products/' . rawurlencode($productId), [], Config::int('cache.ttl.detail')));
    }

    public function pricing(string $attractionId, string $productId, bool $fresh = false): array
    {
        return $this->client->get($this->productPath($attractionId, $productId) . '/pricing', [], $fresh ? 0 : Config::int('cache.ttl.pricing'));
    }

    public function availability(string $attractionId, string $productId, string $from, string $to, bool $fresh = false): array
    {
        return $this->client->get($this->productPath($attractionId, $productId) . '/availability', ['from' => $from, 'to' => $to], $fresh ? 0 : Config::int('cache.ttl.availability'));
    }

    public function purchase(string $attractionId, string $productId, array $body, string $idempotencyKey): Order
    {
        return Order::fromArray($this->client->post($this->productPath($attractionId, $productId) . '/purchase', $body, $idempotencyKey));
    }

    public function purchaseMany(string $attractionId, array $body, string $idempotencyKey): Order
    {
        return Order::fromArray($this->client->post('/attractions/' . rawurlencode($attractionId) . '/orders', $body, $idempotencyKey));
    }

    public function questionValues(string $attractionId, string $productId, string $key, string $q, int $limit, array $answers): array
    {
        $query = ['q' => $q, 'limit' => $limit];
        foreach ($answers as $k => $v) {
            $query['answer.' . $k] = $v;
        }
        return $this->client->get($this->productPath($attractionId, $productId) . '/questions/' . rawurlencode($key) . '/values', $query, 0);
    }

    public function orders(int $limit): array
    {
        return array_map(static fn(array $o) => Order::fromArray($o), $this->client->get('/orders', ['limit' => $limit]));
    }

    public function order(string $orderNumber): Order
    {
        return Order::fromArray($this->client->get('/orders/' . rawurlencode($orderNumber)));
    }

    public function cancel(string $orderNumber, ?string $reason): Order
    {
        return Order::fromArray($this->client->post('/orders/' . rawurlencode($orderNumber) . '/cancel', ['reason' => $reason]));
    }

    public function voucher(string $orderNumber, bool $pdf): array
    {
        return $this->client->raw('/orders/' . rawurlencode($orderNumber) . '/voucher', ['format' => $pdf ? 'pdf' : null]);
    }

    private function productPath(string $attractionId, string $productId): string
    {
        return '/attractions/' . rawurlencode($attractionId) . '/products/' . rawurlencode($productId);
    }
}
