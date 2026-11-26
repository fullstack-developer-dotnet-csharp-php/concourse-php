<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\Legal;
use App\Support\NotFoundException;
use App\Support\Response;
use App\Support\Seo;
use App\Support\View;

final class LegalController
{
    public function show(string $slug): Response
    {
        if (!isset(Legal::available()[$slug])) {
            throw new NotFoundException('Unknown page.');
        }
        $title = Legal::title($slug);
        $seo = Seo::current();
        $seo->title = $title;
        $seo->description = $title . ' for ' . \App\Support\Config::string('site.name') . '.';
        $seo->canonical = abs_url('/' . $slug);
        $seo->breadcrumb([['Home', '/'], [$title, '/' . $slug]]);
        return Response::html(View::render('legal/page', ['title' => $title, 'body' => Legal::html(Legal::text($slug))]));
    }
}
