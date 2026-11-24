<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Catalog;
use App\Support\Config;
use App\Support\Response;

final class SeoController
{
    public function __construct(private Catalog $catalog)
    {
    }

    public function sitemap(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= '<url><loc>' . e(abs_url('/')) . "</loc></url>\n";
        $xml .= '<url><loc>' . e(abs_url('/attractions')) . "</loc></url>\n";
        foreach ($this->catalog->all() as $a) {
            $xml .= '<url><loc>' . e(abs_url($a->path())) . '</loc>';
            if ($a->updatedAt) {
                $xml .= '<lastmod>' . e(substr($a->updatedAt, 0, 10)) . '</lastmod>';
            }
            $xml .= "</url>\n";
        }
        return Response::text($xml . '</urlset>', 'application/xml');
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Allow: /', 'Disallow: /orders', 'Disallow: /book/', '',
            'User-agent: GPTBot', 'Allow: /', '', 'User-agent: ClaudeBot', 'Allow: /', '', 'User-agent: PerplexityBot', 'Allow: /', '',
            'User-agent: Google-Extended', 'Allow: /', '', 'Sitemap: ' . abs_url('/sitemap.xml')];
        return Response::text(implode("\n", $lines) . "\n", 'text/plain');
    }

    public function llms(): Response
    {
        $all = $this->catalog->all();
        $out = '# ' . Config::string('site.name') . "\n\n> " . Config::string('site.tagline') . '. ' . number_format(count($all)) . " attractions with live prices and availability.\n\n";
        $out .= "## Main pages\n\n- [All attractions](" . abs_url('/attractions') . ")\n- [Sitemap](" . abs_url('/sitemap.xml') . ")\n\n";
        $out .= "## Categories\n\n";
        foreach ($this->catalog->categories(20) as $name => $count) {
            $out .= '- [' . $name . '](' . abs_url('/attractions?category=' . rawurlencode($name)) . ') (' . $count . ")\n";
        }
        $out .= "\n## Destinations\n\n";
        foreach ($this->catalog->cities(20) as $name => $count) {
            $out .= '- [' . $name . '](' . abs_url('/attractions?city=' . rawurlencode($name)) . ') (' . $count . ")\n";
        }
        $out .= "\n## Attractions\n\n";
        foreach (array_slice($all, 0, 200) as $a) {
            $out .= '- [' . $a->name . '](' . abs_url($a->path()) . ')' . ($a->city ? ': ' . $a->place() : '') . "\n";
        }
        return Response::text($out, 'text/plain');
    }
}
