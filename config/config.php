<?php
declare(strict_types=1);

$config = [
    'api' => [
        'base_url' => 'https://api-staging.funexconcourse.com/v1',
        'key' => getenv('FUNEX_API_KEY') ?: '',
        'timeout' => 60,
    ],
    'site' => [
        'name' => 'FunEx Tickets',
        'tagline' => 'Attraction tickets, tours and city passes',
        'public_url' => '',
        'locale' => 'en_US',
        'orders_enabled' => true,
    ],
    'cache' => [
        'dir' => __DIR__ . '/../storage/cache',
        'ttl' => [
            'catalog' => 21600,
            'detail' => 3600,
            'pricing' => 300,
            'availability' => 300,
        ],
    ],
    'store' => [
        'driver' => getenv('CATALOG_STORE') ?: 'sleek',
        'sleek' => ['dir' => __DIR__ . '/../storage/db'],
        'mysql' => [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => getenv('DB_NAME') ?: '',
            'user' => getenv('DB_USER') ?: '',
            'password' => getenv('DB_PASSWORD') ?: '',
        ],
    ],
    'sync' => [
        'page_size' => 100,
        'pages_per_run' => 5,
    ],
    'payments' => [
        'enabled' => false,
        'fx_rate' => '',
        'cashfree' => [
            'mode' => 'sandbox',
            'app_id' => '',
            'secret_key' => '',
            'api_version' => '2023-08-01',
            'currency' => 'INR',
        ],
    ],
    'cron' => [
        'key' => '',
    ],
    'legal' => [
        'company' => '',
        'email' => '',
        'terms' => '',
        'privacy' => '',
        'refunds' => '',
    ],
    'log' => [
        'dir' => __DIR__ . '/../storage/logs',
        'level' => 'debug',
        'retention_days' => 14,
        'max_field' => 2000,
    ],
    'debug' => true,
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
