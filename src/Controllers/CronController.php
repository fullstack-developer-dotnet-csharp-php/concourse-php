<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Api\Client;
use App\Services\CatalogSync;
use App\Services\Concourse;
use App\Store\StoreFactory;
use App\Support\Config;
use App\Support\FileCache;
use App\Support\Log;
use App\Support\NotFoundException;
use App\Support\Response;

final class CronController
{
    private const BUDGET_SECONDS = 20;

    public function sync(): Response
    {
        $key = Config::string('cron.key');
        $sent = input($_GET, 'key');
        if ($key === '' || !hash_equals($key, $sent)) {
            Log::warning('Web cron called with a wrong or missing key');
            throw new NotFoundException('Unknown page.');
        }
        $started = microtime(true);
        $runs = [];
        do {
            $status = self::chunk();
            $runs[] = $status;
        } while (!$status['finished'] && microtime(true) - $started < self::BUDGET_SECONDS);
        return Response::text(json_encode(['finished' => $status['finished'], 'runs' => count($runs), 'last' => $status], JSON_UNESCAPED_SLASHES), 'application/json');
    }

    public static function chunk(): array
    {
        $api = new Concourse(new Client(Config::string('api.base_url'), Config::string('api.key'), Config::int('api.timeout'), new FileCache(Config::string('cache.dir'))));
        $sync = new CatalogSync($api, StoreFactory::make(), Config::int('sync.page_size'));
        return $sync->run(Config::int('sync.pages_per_run'));
    }
}
