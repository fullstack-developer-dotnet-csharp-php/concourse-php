<?php use App\Support\Html; ?>
<div class="container page-head">
    <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><a href="/attractions">Attractions</a><span><?= e($attraction->name) ?></span></nav>
    <h1><?= e($attraction->name) ?></h1>
    <p class="muted">
        <?= e($attraction->place()) ?>
        <?php foreach ($attraction->categories as $c): ?><a class="chip chip-sm" href="/attractions?category=<?= e(rawurlencode($c)) ?>"><?= e($c) ?></a><?php endforeach; ?>
    </p>
</div>

<div class="container layout-main"<?= $attraction->contentVersion !== null ? ' data-content-version="' . e($attraction->contentVersion) . '"' : '' ?>>
    <div class="content">
        <?php if ($attraction->heroImageUrl): ?>
            <img class="hero-img" src="<?= e($attraction->heroImageUrl) ?>" alt="<?= e($attraction->name) ?>" width="1200" height="630" fetchpriority="high">
        <?php endif; ?>

        <?php if (count($attraction->images) > 1): ?>
            <section class="gallery" aria-label="Photos">
                <?php foreach (array_slice($attraction->images, 1) as $img): ?>
                    <figure>
                        <img src="<?= e($img['url'] ?? '') ?>" alt="<?= e(($img['alt'] ?? '') !== '' ? $img['alt'] : $attraction->name) ?>" width="400" height="260" loading="lazy">
                        <?php if (($img['caption'] ?? '') !== ''): ?><figcaption><?= e($img['caption']) ?></figcaption><?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($attraction->notice): ?><div class="notice"><?= Html::clean($attraction->notice) ?></div><?php endif; ?>

        <?php if ($attraction->highlights !== []): ?>
            <section aria-labelledby="hl-h">
                <h2 id="hl-h">Highlights</h2>
                <ul class="ticks">
                    <?php foreach ($attraction->highlights as $h): ?><li><?= e(is_array($h) ? ($h['text'] ?? json_encode($h)) : $h) ?></li><?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section aria-labelledby="about-h">
            <h2 id="about-h">About <?= e($attraction->name) ?></h2>
            <div class="prose"><?= Html::clean($attraction->descriptionHtml ?: $attraction->summary) ?></div>
        </section>

        <section aria-labelledby="tix-h" id="tickets">
            <h2 id="tix-h">Tickets and experiences</h2>
            <?php if ($products === []): ?>
                <p class="muted">No tickets are listed for this attraction.</p>
            <?php endif; ?>
            <ul class="product-list">
                <?php foreach ($products as $p): ?>
                    <li class="product-row">
                        <div>
                            <h3><a href="<?= e($p->path($attraction)) ?>"><?= e($p->name) ?></a></h3>
                            <p class="muted">
                                <?= $p->needsEvent() ? 'Select a date' : 'Open-dated' ?>
                                <?php if ($p->durationMinutes): ?> &middot; <?= e($p->durationMinutes) ?> min<?php endif; ?>
                                <?php if ($p->cancellable() === true): ?> &middot; Cancellable<?php elseif ($p->cancellable() === false): ?> &middot; Non-cancellable<?php endif; ?>
                            </p>
                        </div>
                        <div class="product-cta">
                            <?php if ($p->fromPrice() !== null): ?><span class="price"><small>From</small> <?= e(money($p->fromPrice(), $p->offers[0]->currency ?? 'USD')) ?></span><?php endif; ?>
                            <?php if ($p->bookable): ?>
                                <a class="btn btn-primary" href="<?= e($p->path($attraction)) ?>">Check availability</a>
                            <?php else: ?>
                                <span class="badge">Not bookable</span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if ($attraction->goodToKnow !== []): ?>
            <section aria-labelledby="gtk-h">
                <h2 id="gtk-h">Good to know</h2>
                <ul class="ticks">
                    <?php foreach ($attraction->goodToKnow as $g): ?><li><?= e(is_array($g) ? ($g['text'] ?? json_encode($g)) : $g) ?></li><?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($attraction->faq !== []): ?>
            <section aria-labelledby="faq-h">
                <h2 id="faq-h">Frequently asked questions</h2>
                <?php foreach ($attraction->faq as $f): ?>
                    <details class="faq"><summary><?= e($f['q'] ?? '') ?></summary><div class="prose"><?= Html::clean($f['a'] ?? '') ?></div></details>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>

    <aside class="side">
        <div class="side-card">
            <?php if ($attraction->startingPrice !== null): ?><p class="price price-lg"><small>From</small> <?= e(money($attraction->startingPrice, 'USD')) ?></p><?php endif; ?>
            <a class="btn btn-primary btn-block" href="#tickets">See tickets</a>
            <?php if ($attraction->locations !== []): ?>
                <h3><?= count($attraction->locations) > 1 ? 'Locations' : 'Location' ?></h3>
                <?php foreach ($attraction->locations as $loc): ?>
                    <address<?= ($loc['locationId'] ?? '') !== '' ? ' id="location-' . e($loc['locationId']) . '"' : '' ?>><?= e(implode(', ', array_filter([$loc['streetAddress'] ?? null, $loc['city'] ?? null, $loc['state'] ?? null, $loc['zip'] ?? null]))) ?></address>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>
