<?php
declare(strict_types=1);

namespace App;

use App\Api\ApiException;
use App\Api\Client;
use App\Controllers\AdminController;
use App\Controllers\AttractionsController;
use App\Controllers\BookingController;
use App\Controllers\HomeController;
use App\Controllers\OrdersController;
use App\Controllers\PaymentController;
use App\Controllers\SeoController;
use App\Payments\Gateway;
use App\Services\Catalog;
use App\Services\Concourse;
use App\Store\StoreFactory;
use App\Support\Admin;
use App\Support\Config;
use App\Support\ForbiddenException;
use App\Support\NotFoundException;
use App\Support\FileCache;
use App\Support\Log;
use App\Support\Response;
use App\Support\Router;
use App\Support\Seo;
use App\Support\View;
use Throwable;

final class Kernel
{
    public static function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $path = $path === '' ? '/' : $path;
        Log::requestStart();

        try {
            $response = self::routes()->dispatch($method, $path) ?? self::notFound($path);
        } catch (ApiException $e) {
            $unknownAttraction = $e->status === 403 && $e->errorCode === 'NOT_AUTHORIZED' && str_starts_with($path, '/attractions/');
            $notFound = $e->status === 404 || $unknownAttraction;
            if ($notFound) {
                Log::warning('Not found: ' . $path, ['api' => $e->status . ' ' . $e->errorCode]);
            } else {
                Log::exception($e, 'API error while serving ' . $path);
            }
            $response = $notFound ? self::notFound($path, $e) : self::error($e);
        } catch (ForbiddenException $e) {
            Log::warning('Forbidden: ' . $path, ['reason' => $e->getMessage()]);
            $response = self::error($e);
        } catch (NotFoundException $e) {
            Log::warning('Not found: ' . $path, ['reason' => $e->getMessage()]);
            $response = self::notFound($path);
        } catch (Throwable $e) {
            Log::exception($e, 'Unhandled error while serving ' . $path);
            $response = self::error($e);
        }
        $response->send();
    }

    private static function routes(): Router
    {
        $cache = new FileCache(Config::string('cache.dir'));
        $api = new Concourse(new Client(Config::string('api.base_url'), Config::string('api.key'), Config::int('api.timeout'), $cache));
        $catalog = new Catalog($api, $cache, StoreFactory::make());

        View::share('nav', static function () use ($catalog): array {
            try {
                return $catalog->navigation(12);
            } catch (Throwable $e) {
                Log::exception($e, 'Navigation menus unavailable');
                return ['categories' => [], 'cities' => [], 'error' => $e->getMessage()];
            }
        });

        $home = new HomeController($catalog);
        $attractions = new AttractionsController($api, $catalog);
        $gateway = new Gateway($api);
        $booking = new BookingController($api, $gateway);
        $payments = new PaymentController($gateway);
        $admin = new AdminController();
        $orders = new OrdersController($api);
        $seo = new SeoController($catalog);

        $r = new Router();
        $r->add('GET', '/', fn() => $home->index());
        $r->add('GET', '/attractions', fn() => $attractions->index());
        $r->add('GET', '/suggest', fn() => $attractions->suggest());
        $r->add('GET', '/attractions/{id}/{slug}',fn(string $id, string $slug) => $attractions->show($id, $slug));
        $r->add('GET', '/attractions/{id}/{slug}/tickets/{productId}', fn(string $id, string $slug, string $productId) => $attractions->product($id, $slug, $productId));
        $r->add('POST', '/book/{id}/{productId}', fn(string $id, string $productId) => $booking->book($id, $productId));
        if (Config::bool('site.orders_enabled')) {
            $guard = static fn(callable $handler): callable => static fn(string ...$args) => Admin::require() ?? $handler(...$args);
            $r->add('GET', '/orders', $guard(fn() => $orders->index()));
            $r->add('GET', '/orders/{number}', $guard(fn(string $number) => $orders->show($number)));
            $r->add('POST', '/orders/{number}/cancel', $guard(fn(string $number) => $orders->cancel($number)));
            $r->add('GET', '/orders/{number}/voucher', $guard(fn(string $number) => $orders->voucher($number)));
        }
        $r->add('GET', '/admin', fn() => Response::redirect('/admin/settings'));
        $r->add('GET', '/admin/setup', fn() => $admin->setupForm());
        $r->add('POST', '/admin/setup', fn() => $admin->setup());
        $r->add('GET', '/admin/login', fn() => $admin->loginForm());
        $r->add('POST', '/admin/login', fn() => $admin->login());
        $r->add('POST', '/admin/logout', fn() => $admin->logout());
        $r->add('GET', '/admin/settings', fn() => $admin->settings());
        $r->add('POST', '/admin/settings', fn() => $admin->save());
        $r->add('POST', '/admin/test/{target}', fn(string $target) => $admin->test($target));
        $r->add('GET', '/pay/checkout/{id}', fn(string $id) => $payments->checkout($id));
        $r->add('GET', '/pay/return', fn() => $payments->returned());
        $r->add('GET', '/pay/result/{id}', fn(string $id) => $payments->result($id));
        $r->add('POST', '/pay/webhook', fn() => $payments->webhook());
        $r->add('GET', '/favicon.ico', fn() => Response::redirect('/assets/favicon.svg', 301));
        $r->add('GET', '/sitemap.xml', fn() => $seo->sitemap());
        $r->add('GET', '/robots.txt', fn() => $seo->robots());
        $r->add('GET', '/llms.txt', fn() => $seo->llms());
        return $r;
    }

    private static function notFound(string $path, ?ApiException $api = null): Response
    {
        $seo = Seo::current();
        $seo->title = 'Page not found';
        $seo->noindex = true;
        return Response::html(View::render('errors/not_found', ['path' => $path, 'api' => $api]), 404);
    }

    private static function error(Throwable $e): Response
    {
        $seo = Seo::current();
        $seo->title = 'Something went wrong';
        $seo->noindex = true;
        $status = $e instanceof ForbiddenException ? 403 : 500;
        if ($e instanceof ApiException) {
            $status = match (true) {
                $e->status === 0 => 504,
                $e->status === 429 => 503,
                default => 502,
            };
        }
        return Response::html(View::render('errors/error', ['e' => $e, 'debug' => Config::bool('debug')]), $status);
    }
}
