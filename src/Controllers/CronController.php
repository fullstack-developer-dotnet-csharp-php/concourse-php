<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Api\ApiException;
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
    private const FULL_SYNC_EVERY_SECONDS = 79200;

    public function updates(): Response
    {
        $this->guard();
        try {
            $result = self::syncService()->runUpdates(50);
            return Response::text(json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_SLASHES), 'application/json');
        } catch (\Throwable $e) {
            Log::exception($e, 'Web cron update sync failed');
            $status = $e instanceof ApiException ? 502 : 500;
            return Response::text(json_encode(['ok' => false, 'error' => get_class($e), 'message' => $e->getMessage()], JSON_UNESCAPED_SLASHES), 'application/json', $status);
        }
    }

    private function guard(): void
    {
        $key = Config::string('cron.key');
        $sent = input($_GET, 'key');
        if ($key === '' || !hash_equals($key, $sent)) {
            Log::warning('Web cron called with a wrong or missing key');
            throw new NotFoundException('Unknown page.');
        }
    }

    public function sync(): Response
    {
        $this->guard();
        $store = StoreFactory::make();
        $last = $store->meta('sync.last_finished');
        $inProgress = (string) $store->meta('sync.run') !== '';
        if (!$inProgress && $last !== null && $last !== '' && time() - (int) strtotime($last) < self::FULL_SYNC_EVERY_SECONDS && input($_GET, 'force') !== '1') {
            return Response::text(json_encode(['finished' => true, 'skipped' => 'The last full sync was on ' . $last . '. The next one runs after ' . gmdate('c', (int) strtotime($last) + self::FULL_SYNC_EVERY_SECONDS) . '. Add &force=1 to run it now.'], JSON_UNESCAPED_SLASHES), 'application/json');
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
        return self::syncService()->run(Config::int('sync.pages_per_run'));
    }

    private static function syncService(): CatalogSync
    {
        $api = new Concourse(new Client(Config::string('api.base_url'), Config::string('api.key'), Config::int('api.timeout'), new FileCache(Config::string('cache.dir'))));
        return new CatalogSync($api, StoreFactory::make(), Config::int('sync.page_size'));
    }
}
