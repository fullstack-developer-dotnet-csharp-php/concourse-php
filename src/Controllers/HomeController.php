<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Attraction;
use App\Services\Catalog;
use App\Support\Config;
use App\Support\Response;
use App\Support\Seo;
use App\Support\View;

final class HomeController
{
    public function __construct(private Catalog $catalog)
    {
    }

    public function index(): Response
    {
        $all = $this->catalog->all();
        $featured = $this->catalog->deals(8);
        if (count($featured) < 8) {
            $ids = array_map(static fn(Attraction $a) => $a->id, $featured);
            foreach ($all as $a) {
                if (count($featured) >= 8) {
                    break;
                }
                if ($a->heroImageUrl !== null && $a->startingPrice !== null && !in_array($a->id, $ids, true)) {
                    $featured[] = $a;
                }
            }
        }

        $site = Config::string('site.name');
        $seo = Seo::current();
        $seo->title = $site . ' - ' . Config::string('site.tagline');
        $seo->description = 'Browse ' . number_format(count($all)) . ' attractions, tours, theme parks and city passes. See live prices and availability and book tickets online.';
        $seo->canonical = abs_url('/');
        $seo->add(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $site, 'url' => abs_url('/'),
            'potentialAction' => ['@type' => 'SearchAction', 'target' => abs_url('/attractions') . '?q={search_term_string}', 'query-input' => 'required name=search_term_string']]);
        $seo->add(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $site, 'url' => abs_url('/')]);

        return Response::html(View::render('home', [
            'total' => count($all),
            'categories' => $this->catalog->categories(10),
            'cities' => $this->catalog->cities(8),
            'featured' => $featured,
        ]));
    }
}
