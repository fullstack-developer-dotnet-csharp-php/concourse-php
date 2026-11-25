<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Api\Client;
use App\Services\CatalogSync;
use App\Services\Concourse;
use App\Store\StoreFactory;
use App\Support\Config;
use App\Support\FileCache;

$api = new Concourse(new Client(Config::string('api.base_url'), Config::string('api.key'), Config::int('api.timeout'), new FileCache(Config::string('cache.dir'))));
$sync = new CatalogSync($api, StoreFactory::make(), Config::int('sync.page_size'));
$all = in_array('--all', $argv, true);
if (in_array('--updates', $argv, true)) {
    try {
        echo json_encode($sync->runUpdates(50), JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    } catch (Throwable $e) {
        App\Support\Log::exception($e, 'Update sync failed');
        fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
do {
    $status = $sync->run($all ? PHP_INT_MAX >> 40 : Config::int('sync.pages_per_run'));
    echo json_encode($status, JSON_UNESCAPED_SLASHES) . PHP_EOL;
} while ($all && !$status['finished']);
