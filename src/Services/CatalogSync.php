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
        $cursor = $this->store->meta('sync.updates_cursor');
        if ($cursor === null || $cursor === '') {
            $cursor = $this->store->meta('sync.last_finished');
            if ($cursor === null || $cursor === '') {
                throw new \RuntimeException('Run a full sync first (php bin/sync.php --all). The update feed needs a starting point.');
            }
        }
        $started = microtime(true);
        $changed = [];
        $removed = [];
        $pages = 0;
        $next = $cursor;
        while ($pages < $maxPages) {
            $feed = $this->api->contentUpdates($next);
            $pages++;
            foreach ($feed['items'] as $item) {
                $id = (string) ($item['attractionId'] ?? $item['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                if (($item['type'] ?? '') === 'attraction' && ($item['changeType'] ?? '') === 'deleted') {
                    $removed[$id] = true;
                    unset($changed[$id]);
                } else {
                    $changed[$id] = true;
                    unset($removed[$id]);
                }
            }
            $newCursor = $feed['nextCursor'];
            if ($feed['items'] === [] || $newCursor === null || $newCursor === $next) {
                break;
            }
            $next = $newCursor;
        }

        $runId = $this->store->meta('sync.run') ?: bin2hex(random_bytes(8));
        $rows = [];
        foreach (array_keys($changed) as $id) {
            $rows[] = $this->api->attraction($id, true)->toRow();
        }
        if ($rows !== []) {
            $this->store->upsert($rows, $runId);
        }
        foreach (array_keys($removed) as $id) {
            $this->store->remove($id);
        }
        $this->store->setMeta('sync.updates_cursor', $next);

        $status = [
            'mode' => 'updates',
            'feedPages' => $pages,
            'attractionsRefreshed' => count($rows),
            'attractionsRemoved' => count($removed),
            'cursor' => $next,
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
