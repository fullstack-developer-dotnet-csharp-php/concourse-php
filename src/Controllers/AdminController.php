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
use App\Support\Log;
use App\Support\LogReader;
use App\Support\Response;
use App\Support\Seo;
use App\Support\Settings;
use App\Support\SystemCheck;
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

    public function logs(): Response
    {
        if ($gate = Admin::require()) {
            return $gate;
        }
        $files = LogReader::files();
        $file = input($_GET, 'file') ?: ($files[0]['name'] ?? '');
        $level = strtoupper(input($_GET, 'level'));
        $query = input($_GET, 'q');
        $request = input($_GET, 'request');
        $limit = min(1000, max(10, (int) (input($_GET, 'limit') ?: 200)));
        $result = $file !== '' ? LogReader::read($file, $level, $query, $request, $limit) : ['rows' => [], 'matched' => 0, 'total' => 0];
        return $this->page('admin/logs', 'Logs', [
            'files' => $files, 'file' => $file, 'level' => $level, 'q' => $query, 'request' => $request, 'limit' => $limit,
            'result' => $result, 'counts' => $file !== '' ? LogReader::counts($file) : [], 'deleted' => ($_GET['deleted'] ?? '') === '1',
        ]);
    }

    public function logDownload(string $name): Response
    {
        if ($gate = Admin::require()) {
            return $gate;
        }
        return Response::text((string) file_get_contents(LogReader::path($name)), 'text/plain; charset=utf-8');
    }

    public function logDelete(string $name): Response
    {
        if ($gate = Admin::require()) {
            return $gate;
        }
        Csrf::verify();
        LogReader::delete($name);
        return Response::redirect('/admin/logs?deleted=1');
    }

    public function status(): Response
    {
        if ($gate = Admin::require()) {
            return $gate;
        }
        return $this->page('admin/status', 'System check', ['rows' => SystemCheck::run(true)]);
    }

    public function sync(): Response
    {
        if ($gate = Admin::require()) {
            return $gate;
        }
        Csrf::verify();
        try {
            return $this->json(['ok' => true] + CronController::chunk());
        } catch (Throwable $e) {
            Log::exception($e, 'Catalog sync from the admin page failed');
            return $this->json(['ok' => false, 'message' => $e->getMessage()]);
        }
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
        return Response::text(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP), 'application/json');
    }

    private function next(): string
    {
        $next = input($_POST + $_GET, 'next');
        return str_starts_with($next, '/') && !str_starts_with($next, '//') ? $next : '/admin/settings';
    }
}
