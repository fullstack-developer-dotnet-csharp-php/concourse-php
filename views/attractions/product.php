<?php
use App\Models\Offer;
use App\Support\Config;
use App\Support\Html;


?>
<div class="container page-head">
    <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><a href="/attractions">Attractions</a><a href="<?= e($attraction->path()) ?>"><?= e($attraction->name) ?></a><span><?= e($product->name) ?></span></nav>
    <h1><?= e($product->name) ?></h1>
    <p class="muted"><?= e($attraction->name) ?><?php if ($product->durationMinutes): ?> &middot; <?= e($product->durationMinutes) ?> min<?php endif; ?>
        <?php if ($product->cancellable() === true): ?> &middot; Cancellable<?php elseif ($product->cancellable() === false): ?> &middot; Non-cancellable<?php endif; ?></p>
</div>

<div class="container layout-main"<?= $product->contentVersion !== null ? ' data-content-version="' . e($product->contentVersion) . '"' : '' ?>>
    <div class="content">
        <?= App\Support\View::partial('gallery', ['images' => $product->images, 'hero' => $attraction->heroImageUrl, 'name' => $product->name]) ?>

        <?php if ($product->description): ?><div class="prose"><?= Html::clean($product->description) ?></div><?php endif; ?>

        <?php if ($product->details !== []): ?>
            <dl class="facts">
                <?php foreach ($product->details as $d): ?><dt><?= e($d['label'] ?? '') ?></dt><dd><?= e($d['value'] ?? '') ?></dd><?php endforeach; ?>
            </dl>
        <?php endif; ?>

        <?= App\Support\View::partial('booking', get_defined_vars()) ?>

        <?php if ($product->faq !== []): ?>
            <section aria-labelledby="pfaq-h">
                <h2 id="pfaq-h">Frequently asked questions</h2>
                <?php foreach ($product->faq as $f): ?>
                    <details class="faq"><summary><?= e($f['q'] ?? '') ?></summary><div class="prose"><?= Html::clean($f['a'] ?? '') ?></div></details>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>

    <aside class="side">
        <div class="side-card">
            <h3><?= e($attraction->name) ?></h3>
            <?php if ($attraction->heroImageUrl): ?><img src="<?= e($attraction->heroImageUrl) ?>" alt="" width="320" height="180" loading="lazy"><?php endif; ?>
            <?php foreach ($product->offers as $o): ?>
                <?php if ($o->validFrom || $o->validTo): ?>
                    <p class="small"><strong><?= e($o->label) ?> validity:</strong> <?= e(trim(($o->validFrom ? 'from ' . substr($o->validFrom, 0, 10) : '') . ' ' . ($o->validTo ? 'until ' . substr($o->validTo, 0, 10) : ''))) ?></p>
                <?php endif; ?>
                <?php if ($o->cancellationTerms || $o->cutoffMinutes): ?>
                    <p class="small"><strong><?= e($o->label) ?>:</strong> <?= e($o->cancellationTerms ?? 'Cancel up to ' . $o->cutoffMinutes . ' minutes before the visit.') ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
            <a class="btn btn-ghost btn-block" href="<?= e($attraction->path()) ?>">All tickets</a>
        </div>
    </aside>
</div>
