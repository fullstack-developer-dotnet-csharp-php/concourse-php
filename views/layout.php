<?php
use App\Support\Config;

$site = Config::string('site.name');
$title = $seo->title !== '' ? $seo->title : $site;
$canonical = $seo->canonical !== '' ? $seo->canonical : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if ($seo->description !== ''): ?>
<meta name="description" content="<?= e($seo->description) ?>">
<?php endif; ?>
<meta name="robots" content="<?= $seo->noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1' ?>">
<?php if ($canonical !== ''): ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($site) ?>">
<meta property="og:type" content="<?= e($seo->type) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:locale" content="<?= e(Config::string('site.locale')) ?>">
<?php if ($seo->description !== ''): ?>
<meta property="og:description" content="<?= e($seo->description) ?>">
<?php endif; ?>
<?php if ($canonical !== ''): ?>
<meta property="og:url" content="<?= e($canonical) ?>">
<?php endif; ?>
<?php if ($seo->image !== ''): ?>
<meta property="og:image" content="<?= e($seo->image) ?>">
<meta name="twitter:image" content="<?= e($seo->image) ?>">
<?php endif; ?>
<meta name="twitter:card" content="<?= $seo->image !== '' ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="theme-color" content="#0e6b6f">
<meta name="referrer" content="no-referrer">
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="alternate" type="text/plain" href="/llms.txt" title="LLMs">
<link rel="stylesheet" href="/assets/css/site.css?v=<?= e(filemtime(__DIR__ . '/../public/assets/css/site.css')) ?>">
<link rel="stylesheet" href="/assets/css/ui.css?v=<?= e(filemtime(__DIR__ . '/../public/assets/css/ui.css')) ?>">
<?= $seo->renderJsonLd() ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container header-row">
        <a class="brand" href="/" aria-label="<?= e($site) ?> home"><span class="brand-mark" aria-hidden="true"></span><?= e($site) ?></a>
        <form class="header-search" action="/attractions" method="get" role="search">
            <label class="sr-only" for="hq">Search attractions</label>
            <input id="hq" type="search" name="q" value="<?= e(input($_GET, 'q')) ?>" placeholder="Search attractions, cities, tours" autocomplete="off">
            <button type="submit">Search</button>
        </form>
        <?php $nav = App\Support\View::shared('nav') ?? ['categories' => [], 'cities' => []]; ?>
        <nav class="main-nav" aria-label="Main">
            <?php foreach ([['Destinations', $nav['cities'], 'city'], ['Categories', $nav['categories'], 'category']] as [$label, $items, $param]): ?>
                <?php if ($items): ?>
                <details class="dd">
                    <summary><?= e($label) ?></summary>
                    <div class="dd-panel">
                        <ul>
                            <?php foreach ($items as $name => $count): ?>
                                <li><a href="/attractions?<?= e($param) ?>=<?= e(rawurlencode((string) $name)) ?>"><?= e($name) ?> <span class="muted">(<?= e($count) ?>)</span></a></li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="dd-all" href="/attractions">View all attractions</a>
                    </div>
                </details>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (isset($nav['error'])): ?><span class="error small" title="<?= e($nav['error']) ?>">Menus unavailable</span><?php endif; ?>
            <a href="/attractions">Attractions</a>
        </nav>
        <?php if (Config::bool('auth.google.enabled')): ?>
        <div class="header-account">
            <?php if ($customer = App\Support\Customer::user()): ?>
                <span class="account-chip">
                    <?php if ($customer['picture'] !== ''): ?><img src="<?= e($customer['picture']) ?>" alt="" width="22" height="22" loading="lazy">
                    <?php endif; ?>
                    <?= e($customer['name']) ?>
                </span>
                <form method="post" action="/auth/logout" class="inline-form">
                    <?= App\Support\Csrf::field() ?>
                    <input type="hidden" name="next" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
                    <button type="submit" class="btn btn-ghost">Sign out</button>
                </form>
            <?php else: ?>
                <a class="btn btn-ghost" href="/auth/google?next=<?= e(rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/'))) ?>">Sign in with Google</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</header>
<main id="main">
<?= $content ?>
</main>
<footer class="site-footer">
    <div class="container footer-row">
        <div>
            <strong><?= e($site) ?></strong>
            <p><?= e(Config::string('site.tagline')) ?>. Prices and availability are live from the supplier.</p>
        </div>
        <ul>
            <li><a href="/attractions">All attractions</a></li>
            <?php foreach (App\Support\Legal::available() as $slug => $title): ?><li><a href="/<?= e($slug) ?>"><?= e($title) ?></a></li><?php endforeach; ?>
            <li><a href="/sitemap.xml">Sitemap</a></li>
            <li><a href="/llms.txt">For AI assistants</a></li>
        </ul>
    </div>
</footer>
<script src="/assets/js/gallery.js?v=<?= e(filemtime(__DIR__ . '/../public/assets/js/gallery.js')) ?>" defer></script>
<script src="/assets/js/app.js?v=<?= e(filemtime(__DIR__ . '/../public/assets/js/app.js')) ?>" defer></script>
</body>
</html>
