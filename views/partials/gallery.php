<?php
$list = [];
foreach ($images as $img) {
    if (!empty($img['url']) && !in_array($img['role'] ?? null, ['logo', 'card', 'banner-mobile'], true)) {
        $list[] = ['url' => (string) $img['url'], 'alt' => ($img['alt'] ?? '') !== '' ? (string) $img['alt'] : $name, 'caption' => (string) ($img['caption'] ?? '')];
    }
}
if ($list === [] && !empty($hero)) {
    $list[] = ['url' => (string) $hero, 'alt' => $name, 'caption' => ''];
}
$total = count($list);
if ($total === 0) {
    return;
}
?>
<section class="gx" data-gallery aria-roledescription="carousel" aria-label="Photos of <?= e($name) ?>">
    <div class="gx-stage">
        <ul class="gx-track" tabindex="0" aria-live="polite">
            <?php foreach ($list as $i => $img): ?>
                <li class="gx-slide" role="group" aria-roledescription="slide" aria-label="<?= e($i + 1) ?> of <?= e($total) ?>">
                    <button type="button" class="gx-open" data-index="<?= e($i) ?>" aria-label="Enlarge photo <?= e($i + 1) ?>">
                        <img src="<?= e($img['url']) ?>" alt="<?= e($img['alt']) ?>" width="1200" height="675"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?> decoding="async" data-full="<?= e($img['url']) ?>" data-caption="<?= e($img['caption']) ?>">
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total > 1): ?>
            <button type="button" class="gx-nav gx-prev" aria-label="Previous photo"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            <button type="button" class="gx-nav gx-next" aria-label="Next photo"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <?php endif; ?>
        <span class="gx-count" aria-hidden="true"><span data-gx-now>1</span> / <?= e($total) ?></span>
        <button type="button" class="gx-zoom" data-index="0"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg><?= $total > 1 ? 'All ' . e($total) . ' photos' : 'Enlarge' ?></button>
    </div>
    <?php if ($total > 1): ?>
        <ul class="gx-thumbs" aria-label="Choose a photo">
            <?php foreach ($list as $i => $img): ?>
                <li><button type="button" class="gx-thumb<?= $i === 0 ? ' is-on' : '' ?>" data-index="<?= e($i) ?>" aria-label="Show photo <?= e($i + 1) ?>"><img src="<?= e($img['url']) ?>" alt="" width="120" height="80" loading="lazy" decoding="async"></button></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
