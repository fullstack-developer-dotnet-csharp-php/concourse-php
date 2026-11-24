<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Attraction;
use App\Support\Config;
use App\Store\CatalogStore;
use App\Support\FileCache;

final class Catalog
{
    private ?array $all = null;

    public function __construct(private Concourse $api, private FileCache $cache, private CatalogStore $store)
    {
    }

    public function all(): array
    {
        if ($this->all !== null) {
            return $this->all;
        }
        $rows = $this->store->all();
        if ($rows === []) {
            throw new \RuntimeException('The catalog store is empty. Run "php bin/sync.php" to load attractions from the API.');
        }
        return $this->all = array_map(static fn(array $r) => Attraction::fromArray($r), $rows);
    }

    public function navigation(int $limit): array
    {
        $key = 'catalog-nav-' . $limit . '-' . ($this->store->meta('sync.last_finished') ?? 'none');
        $cached = $this->cache->get($key, Config::int('cache.ttl.catalog'));
        if ($cached === null) {
            $cached = ['categories' => $this->categories($limit), 'cities' => $this->cities($limit)];
            $this->cache->put($key, $cached);
        }
        return $cached;
    }

    public function categories(int $limit): array
    {
        $counts = [];
        foreach ($this->all() as $a) {
            foreach ($a->categories as $c) {
                $counts[$c] = ($counts[$c] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    public function cities(int $limit): array
    {
        $counts = [];
        foreach ($this->all() as $a) {
            if ($a->city !== null && $a->city !== '') {
                $counts[$a->city] = ($counts[$a->city] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    public function search(string $q, int $limit): array
    {
        $tokens = $this->tokens($q);
        if ($tokens === []) {
            return [];
        }
        $pool = [];
        foreach ($this->api->attractionsPage(1, 100, $q)['items'] as $a) {
            $pool[$a->id] = $a;
        }
        $anchor = array_reduce($tokens, static fn(?string $c, string $t) => $c === null || mb_strlen($t) > mb_strlen($c) ? $t : $c);
        foreach ($this->store->search($anchor, 1000) as $row) {
            $a = Attraction::fromArray($row);
            $pool[$a->id] = $a;
        }
        $ranked = $this->rank($pool, $tokens, $q, false);
        if ($ranked === []) {
            $ranked = $this->rank($this->all(), $tokens, $q, true);
        }
        return array_slice($ranked, 0, $limit);
    }

    public function suggest(string $q, int $limit): array
    {
        return $this->search($q, $limit);
    }

    private function tokens(string $q): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($q)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique($parts));
    }

    private function rank(array $pool, array $tokens, string $q, bool $fuzzy): array
    {
        $phrase = mb_strtolower(trim($q));
        $scored = [];
        foreach ($pool as $a) {
            $nameWords = $this->tokens($a->name);
            $name = mb_strtolower($a->name);
            $place = mb_strtolower(trim(($a->city ?? '') . ' ' . ($a->country ?? '')));
            $summary = mb_strtolower((string) $a->summary);
            $score = 0;
            foreach ($tokens as $t) {
                $hit = $this->wordPrefix($nameWords, $t);
                if ($hit === 1) {
                    $score += 100;
                } elseif ($hit === 2) {
                    $score += 40;
                } elseif (mb_strpos($place, $t) !== false) {
                    $score += 30;
                } elseif (mb_strpos($summary, $t) !== false) {
                    $score += 5;
                } elseif ($fuzzy && $this->nearWord($nameWords, $t)) {
                    $score += 20;
                } else {
                    continue 2;
                }
            }
            if (mb_strpos($name, $phrase) !== false) {
                $score += 500 + (mb_strpos($name, $phrase) === 0 ? 200 : 0);
            }
            $scored[] = [$score + ($a->bookable ? 50 : 0), mb_strlen($name), $a];
        }
        usort($scored, static fn(array $x, array $y) => [$y[0], $x[1]] <=> [$x[0], $y[1]]);
        return array_map(static fn(array $s) => $s[2], $scored);
    }

    private function wordPrefix(array $words, string $t): int
    {
        foreach ($words as $w) {
            if ($w === $t) {
                return 1;
            }
        }
        foreach ($words as $w) {
            if (str_starts_with($w, $t)) {
                return 1;
            }
        }
        foreach ($words as $w) {
            if (mb_strlen($t) >= 3 && mb_strpos($w, $t) !== false) {
                return 2;
            }
        }
        return 0;
    }

    private function nearWord(array $words, string $t): bool
    {
        $len = mb_strlen($t);
        if ($len < 4) {
            return false;
        }
        $max = $len >= 8 ? 2 : 1;
        foreach ($words as $w) {
            if (abs(mb_strlen($w) - $len) <= $max && levenshtein($t, $w) <= $max) {
                return true;
            }
            if (mb_strlen($w) > $len && levenshtein($t, mb_substr($w, 0, $len)) <= $max) {
                return true;
            }
        }
        return false;
    }

    public function deals(int $limit): array
    {
        $deals = array_values(array_filter($this->all(), static fn(Attraction $a) => $a->promotionText !== null && $a->heroImageUrl !== null));
        return array_slice($deals, 0, $limit);
    }

    public function filter(array $items, ?string $city, ?string $category): array
    {
        return array_values(array_filter($items, static function (Attraction $a) use ($city, $category): bool {
            if ($city !== null && $city !== '' && strcasecmp((string) $a->city, $city) !== 0) {
                return false;
            }
            if ($category !== null && $category !== '' && !in_array($category, $a->categories, true)) {
                return false;
            }
            return true;
        }));
    }
}
