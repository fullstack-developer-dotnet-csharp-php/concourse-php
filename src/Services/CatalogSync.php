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

    public function runUpdates(int $maxPages): array
    {
        $cursor = $this->store->meta('sync.events_cursor');
        $skipBefore = null;
        if ($cursor === null || $cursor === '') {
            $finished = $this->store->meta('sync.last_finished');
            if ($finished === null || $finished === '') {
                throw new \RuntimeException('Run a full sync first (php bin/sync.php --all). The change feed needs a starting point.');
            }
            $cursor = '0';
            $skipBefore = strtotime($finished);
        }
        $started = microtime(true);
        $changed = [];
        $removed = [];
        $resync = false;
        $pages = 0;
        $after = (int) $cursor;
        try {
            do {
                $feed = $this->api->events($after, 100);
                $pages++;
                foreach ($feed['events'] as $event) {
                    if ($skipBefore !== null && strtotime((string) ($event['createdAt'] ?? '')) < $skipBefore) {
                        continue;
                    }
                    if (($event['type'] ?? '') === 'catalog.resync') {
                        $resync = true;
                        continue;
                    }
                    foreach ($event['changes'] ?? [] as $change) {
                        $id = (string) (($change['resource'] ?? '') === 'product' ? ($change['attractionId'] ?? '') : ($change['id'] ?? ''));
                        if ($id !== '') {
                            $changed[$id] = true;
                        }
                    }
                }
                $after = max($after, $feed['nextAfter']);
            } while ($feed['hasMore'] && $pages < $maxPages);
        } catch (\App\Api\ApiException $e) {
            if ($e->status === 410 || $e->errorCode === 'EVENTS_CURSOR_EXPIRED') {
                $this->store->setMeta('sync.events_cursor', '');
                throw new \RuntimeException('The change feed cursor expired. Run a full sync (php bin/sync.php --all) to start again.', 0, $e);
            }
            throw $e;
        }

        $rows = [];
        foreach (array_keys($changed) as $id) {
            try {
                $rows[] = $this->api->attraction($id, true)->toRow();
            } catch (\App\Api\ApiException $e) {
                if ($e->status === 404 || $e->status === 403) {
                    $removed[$id] = true;
                    continue;
                }
                throw $e;
            }
        }
        $runId = $this->store->meta('sync.run') ?: bin2hex(random_bytes(8));
        if ($rows !== []) {
            $this->store->upsert($rows, $runId);
        }
        foreach (array_keys($removed) as $id) {
            $this->store->remove($id);
        }
        $this->store->setMeta('sync.events_cursor', (string) $after);

        $status = [
            'mode' => 'updates',
            'feedPages' => $pages,
            'attractionsRefreshed' => count($rows),
            'attractionsRemoved' => count($removed),
            'fullResyncAdvised' => $resync,
            'cursor' => $after,
            'stored' => $this->store->count(),
            'seconds' => round(microtime(true) - $started, 1),
        ];
        Log::info('Catalog update sync', $status);
        return $status;
    }

    public function run(int $pagesPerRun): array
    {
        $runId = $this->store->meta('sync.run') ?: bin2hex(random_bytes(8));
        $page = (int) ($this->store->meta('sync.next_page') ?? 1);
        $this->store->setMeta('sync.run', $runId);
        if ($page === 1) {
            $this->store->setMeta('sync.events_head', (string) $this->api->events(null, 1)['nextAfter']);
        }
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
            $head = (string) $this->store->meta('sync.events_head');
            if ($head !== '') {
                $this->store->setMeta('sync.events_cursor', $head);
                $this->store->setMeta('sync.events_head', '');
            }
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
