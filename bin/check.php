<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Support\Config;
use App\Support\Settings;

$fail = 0;
$row = static function (string $label, bool $ok, string $detail = '') use (&$fail): void {
    if (!$ok) {
        $fail++;
    }
    echo str_pad($ok ? 'OK' : 'FAIL', 6) . str_pad($label, 34) . $detail . PHP_EOL;
};

$row('PHP 8.2 or newer', PHP_VERSION_ID >= 80200, PHP_VERSION);
foreach (['curl', 'json', 'mbstring', 'openssl'] as $ext) {
    $row('Extension ' . $ext, extension_loaded($ext));
}
if (Config::string('store.driver') === 'mysql') {
    $row('Extension pdo_mysql', extension_loaded('pdo_mysql'));
}
$root = dirname(__DIR__);
foreach (['storage', 'storage/logs', 'storage/cache', 'storage/db', 'storage/payments', 'storage/sessions'] as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    $row('Writable ' . $dir, is_dir($path) && is_writable($path), $path);
}
$row('API base URL set', Config::string('api.base_url') !== '', Config::string('api.base_url'));
$row('API key set', Config::string('api.key') !== '');
$row('Admin password set', Settings::hasSaved('admin.password_hash'), Settings::hasSaved('admin.password_hash') ? '' : 'open /admin/setup');
$row('Public URL set', Config::string('site.public_url') !== '', Config::string('site.public_url'));
$row('Catalog synced', is_dir($root . '/storage/db') && count(glob($root . '/storage/db/*') ?: []) > 0, 'run: php bin/sync.php --all');

echo PHP_EOL . ($fail === 0 ? 'Ready.' : $fail . ' check(s) failed.') . PHP_EOL;
exit($fail === 0 ? 0 : 1);
