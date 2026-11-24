<article class="card">
    <a class="card-link" href="<?= e($a->path()) ?>" aria-label="<?= e($a->name) ?>">
        <div class="card-media">
            <?php if ($a->heroImageUrl): ?>
                <img src="<?= e($a->heroImageUrl) ?>" alt="<?= e($a->name) ?>" width="480" height="320" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="card-noimg" aria-hidden="true"></div>
            <?php endif; ?>
            <?php if ($a->promotionText): ?><span class="badge badge-deal"><?= e($a->promotionText) ?></span><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($a->place() !== ''): ?><p class="card-place"><?= e($a->place()) ?></p><?php endif; ?>
            <h3 class="card-title"><?= e($a->name) ?></h3>
            <?php if ($a->summary): ?><p class="card-text"><?= e(plain_text($a->summary, 110)) ?></p><?php endif; ?>
            <p class="card-foot">
                <?php if ($a->startingPrice !== null): ?>
                    <span class="price"><small>From</small> <?= e(money($a->startingPrice, 'USD')) ?></span>
                <?php else: ?>
                    <span class="muted">Price on request</span>
                <?php endif; ?>
                <?php if (!$a->bookable): ?><span class="badge">Not bookable</span><?php endif; ?>
            </p>
        </div>
    </a>
</article>
