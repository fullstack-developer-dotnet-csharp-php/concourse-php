<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'SleekDB\\')) {
        $vendored = __DIR__ . '/../lib/SleekDB/' . str_replace('\\', '/', substr($class, strlen('SleekDB\\'))) . '.php';
        if (is_file($vendored)) {
            require $vendored;
        }
        return;
    }
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/Support/helpers.php';

App\Support\Config::load(App\Support\Settings::applyTo(require __DIR__ . '/../config/config.php'));
date_default_timezone_set('UTC');

App\Support\Log::init(
    App\Support\Config::string('log.dir'),
    App\Support\Config::string('log.level'),
    App\Support\Config::int('log.retention_days'),
    App\Support\Config::int('log.max_field')
);
App\Support\Log::register();
