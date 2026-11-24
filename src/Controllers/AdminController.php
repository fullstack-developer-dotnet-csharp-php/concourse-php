<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Api\Client;
use App\Payments\Cashfree;
use App\Payments\CashfreeException;
use App\Services\Concourse;
use App\Store\StoreFactory;
use App\Support\Admin;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\FileCache;
use App\Support\Response;
use App\Support\Seo;
use App\Support\Settings;
use App\Support\View;
use Throwable;

final class AdminController
{
    public function setupForm(): Response
    {
        if (Admin::isConfigured()) {
            return Response::redirect('/admin/login');
        }
        Admin::ensureSetupToken();
        return $this->page('admin/setup', 'Admin setup', ['tokenPath' => Admin::setupTokenPath(), 'error' => null]);
    }

    public function setup(): Response
    {
        Csrf::verify();
        Admin::completeSetup(input($_POST, 'token'), (string) ($_POST['password'] ?? ''));
        return Response::redirect('/admin/settings');
    }

    public function loginForm(): Response
    {
        if (!Admin::isConfigured()) {
            return Response::redirect('/admin/setup');
        }
        return $this->page('admin/login', 'Admin login', ['next' => $this->next()]);
    }

    public function login(): Response
    {
        Csrf::verify();
        Admin::attempt((string) ($_POST['password'] ?? ''));
        return Response::redirect($this->next());
    }

    public function logout(): Response
    {
        Csrf::verify();
        Admin::logout();
        return Response::redirect('/');
    }

    public function settings(): Response
    {
        if ($gate = Admin::require()) {
            return $gate;
        }
        return $this->settingsPage([], null);
    }

    public function save(): Response
    {
        if ($gate = Admin::require()) {
            return $gate;
        }
        Csrf::verify();
        $submitted = is_array($_POST['s'] ?? null) ? $_POST['s'] : [];
        $errors = Settings::save($submitted);
        $password = (string) ($_POST['admin_password_new'] ?? '');
        if ($errors === [] && $password !== '') {
            Admin::changePassword($password);
        }
        if ($errors !== []) {
            return $this->settingsPage($errors, null, $submitted);
        }
        return Response::redirect('/admin/settings?saved=1');
    }

    public function test(string $target): Response
    {
        if ($gate = Admin::require()) {
            return $gate;
        }
        Csrf::verify();
        try {
            $message = match ($target) {
                'api' => $this->testApi(),
                'cashfree' => $this->testCashfree(),
                'store' => $this->testStore(),
                default => throw new \InvalidArgumentException('Unknown test: ' . $target),
            };
            return $this->json(['ok' => true, 'message' => $message]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    private function testApi(): string
    {
        $api = new Concourse(new Client(Config::string('api.base_url'), Config::string('api.key'), Config::int('api.timeout'), new FileCache(Config::string('cache.dir'))));
        $page = $api->attractionsPage(1, 1, null, true);
        return 'Connected. The API lists ' . number_format($page['total']) . ' attractions.';
    }

    private function testCashfree(): string
    {
        $cf = new Cashfree(Config::string('payments.cashfree.mode'), Config::string('payments.cashfree.app_id'), Config::string('payments.cashfree.secret_key'), Config::string('payments.cashfree.api_version'));
        try {
            $cf->getOrder('connection_test_does_not_exist');
        } catch (CashfreeException $e) {
            if ($e->status === 404 || $e->errorCode === 'order_not_found') {
                return 'Cashfree accepted the credentials (' . $cf->mode() . ' mode).';
            }
            throw $e;
        }
        return 'Cashfree answered (' . $cf->mode() . ' mode).';
    }

    private function testStore(): string
    {
        $store = StoreFactory::make();
        $last = $store->meta('sync.last_finished');
        return 'Storage works (' . Config::string('store.driver') . '). ' . number_format($store->count()) . ' attractions stored' . ($last ? ', last full sync ' . $last . '.' : ', no full sync has finished yet.');
    }

    private function settingsPage(array $errors, ?string $flash, ?array $posted = null): Response
    {
        return $this->page('admin/settings', 'Settings', [
            'schema' => Settings::schema(),
            'errors' => $errors,
            'posted' => $posted,
            'saved' => ($_GET['saved'] ?? '') === '1',
        ]);
    }

    private function page(string $template, string $title, array $data): Response
    {
        $seo = Seo::current();
        $seo->title = $title . ' | ' . Config::string('site.name');
        $seo->noindex = true;
        return Response::html(View::render($template, $data));
    }

    private function json(array $data): Response
    {
        return Response::text(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'application/json');
    }

    private function next(): string
    {
        $next = input($_POST + $_GET, 'next');
        return str_starts_with($next, '/') && !str_starts_with($next, '//') ? $next : '/admin/settings';
    }
}
