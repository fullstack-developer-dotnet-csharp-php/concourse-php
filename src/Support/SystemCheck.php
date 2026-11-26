<?php
declare(strict_types=1);

namespace App\Support;

use App\Store\StoreFactory;
use Throwable;

final class SystemCheck
{
    public const MIN_PHP = 80000;

    public static function run(bool $web): array
    {
        $rows = [];
        $add = static function (string $label, ?bool $ok, string $detail = '', string $fix = '') use (&$rows): void {
            $rows[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail, 'fix' => $ok === true ? '' : $fix];
        };
        $root = dirname(__DIR__, 2);

        $add('PHP 8.0 or newer', PHP_VERSION_ID >= self::MIN_PHP, PHP_VERSION, 'Choose PHP 8.0 or newer in the hosting control panel (Select PHP Version).');
        foreach (['curl', 'json', 'mbstring', 'openssl'] as $ext) {
            $add('Extension ' . $ext, extension_loaded($ext), '', 'Enable the ' . $ext . ' extension in the hosting control panel.');
        }
        $driver = Config::string('store.driver');
        if ($driver === 'mysql') {
            $add('Extension pdo_mysql', extension_loaded('pdo_mysql'), '', 'Enable pdo_mysql in the hosting control panel, or switch the storage driver to SleekDB on the settings page.');
        }
        foreach (['storage', 'storage/logs', 'storage/cache', 'storage/db', 'storage/payments', 'storage/sessions'] as $dir) {
            $path = $root . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $add('Writable ' . $dir, is_dir($path) && is_writable($path), '', 'Set the folder permission to 755 or 775 so PHP can write to it.');
        }
        $limit = (int) ini_get('max_execution_time');
        $add('Script time limit', $limit === 0 || $limit >= 30, $limit === 0 ? 'no limit' : $limit . ' seconds', 'Lower "Sync pages per run" on the settings page so one run finishes inside the limit.');

        $add('API base URL set', Config::string('api.base_url') !== '', Config::string('api.base_url'), 'Fill it in on the settings page.');
        $add('API key set', Config::string('api.key') !== '', '', 'Paste the FunEx API key on the settings page.');
        $add('Admin password set', Settings::hasSaved('admin.password_hash'), '', 'Open /admin/setup.');
        $add('Public URL set', Config::string('site.public_url') !== '', Config::string('site.public_url'), 'Enter the full https address on the settings page so sitemap, canonical links and payment return links are correct.');
        $public = Config::string('site.public_url');
        $address = $public !== '' ? $public : base_url();
        $local = (bool) preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\])(:|/|$)#', $address);
        $add('Site uses https', $local ? null : str_starts_with($address, 'https://'), $local ? 'local test address' : '', 'Turn on the free SSL certificate in the hosting panel, then set the public URL to https. Payments and the admin login need https.');

        try {
            $store = StoreFactory::make();
            $count = $store->count();
            $last = $store->meta('sync.last_finished');
            $add('Catalog storage works (' . $driver . ')', true, number_format($count) . ' attractions');
            $add('Catalog synced', $count > 0 && $last !== null && $last !== '', $last ? 'last full sync ' . $last : 'no full sync yet', 'Press "Run sync" on the System page, or run php bin/sync.php --all.');
        } catch (Throwable $e) {
            $add('Catalog storage works (' . $driver . ')', false, $e->getMessage(), 'Check the storage settings.');
        }

        $add('Web cron key set', Config::string('cron.key') !== '' ? true : null, Config::string('cron.key') !== '' ? '' : 'not set (optional)', 'Optional. Set it on the settings page if your host has no cron, then call /cron/sync?key=... from a free cron service.');
        $add('Payments', null, Config::bool('payments.enabled') ? 'on (' . Config::string('payments.cashfree.mode') . ')' : 'off');

        if ($web) {
            foreach (self::exposure() as $row) {
                $add($row[0], $row[1], $row[2], 'Block web access to this folder. The root .htaccess does it on Apache and LiteSpeed. On nginx add a deny rule, or point the web root at public/.');
            }
        }
        return $rows;
    }

    public static function failures(array $rows): int
    {
        return count(array_filter($rows, static fn(array $r) => $r['ok'] === false));
    }

    private static function exposure(): array
    {
        if (!function_exists('curl_init')) {
            return [];
        }
        $out = [];
        $targets = ['storage/settings.json' => 'Settings file is private', 'config/config.php' => 'Config folder is private', 'src/bootstrap.php' => 'Source folder is private'];
        foreach ($targets as $path => $label) {
            $ch = curl_init(base_url() . '/' . $path);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_NOBODY => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => false]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($status === 0) {
                $out[] = [$label, null, 'could not test from the server'];
                continue;
            }
            $out[] = [$label, $status === 403 || $status === 404, 'HTTP ' . $status];
        }
        return $out;
    }
}
