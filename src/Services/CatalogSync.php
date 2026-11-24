<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Attraction;
use App\Store\CatalogStore;
use App\Support\Log;

final class CatalogSync
{
    public function __construct(private Concourse $api, private CatalogStore $store, private int $pageSize)
    {
    }

    public function run(int $pagesPerRun): array
    {
        $runId = $this->store->meta('sync.run') ?: bin2hex(random_bytes(8));
        $page = (int) ($this->store->meta('sync.next_page') ?? 1);
        $this->store->setMeta('sync.run', $runId);
        $fetched = 0;
        $finished = false;
        $total = 0;
        $started = microtime(true);

        for ($i = 0; $i < $pagesPerRun; $i++) {
            $result = $this->api->attractionsPage($page, $this->pageSize, null, true);
            $total = $result['total'];
            $this->store->upsert(array_map(static fn(Attraction $a) => $a->toRow(), $result['items']), $runId);
            $fetched += count($result['items']);
            $page++;
            $this->store->setMeta('sync.next_page', (string) $page);
            if ($result['items'] === [] || ($page - 1) * $this->pageSize >= $total) {
                $finished = true;
                break;
            }
        }

        $removed = 0;
        if ($finished) {
            $removed = $this->store->removeNotSeen($runId);
            $this->store->setMeta('sync.last_finished', gmdate('c'));
            $this->store->setMeta('sync.run', '');
            $this->store->setMeta('sync.next_page', '1');
        }

        $status = [
            'finished' => $finished,
            'fetched' => $fetched,
            'nextPage' => $finished ? 1 : $page,
            'apiTotal' => $total,
            'stored' => $this->store->count(),
            'removed' => $removed,
            'seconds' => round(microtime(true) - $started, 1),
        ];
        Log::info('Catalog sync run', $status);
        return $status;
    }
}
