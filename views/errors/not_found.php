<div class="container page-head">
    <h1>Page not found</h1>
    <p class="muted"><?= e($path) ?></p>
</div>
<div class="container">
    <div class="error-box">
        <?php if ($api): ?>
            <p><strong><?= e($api->errorCode) ?></strong>: <?= e($api->getMessage()) ?></p>
            <p class="muted"><?= e($api->url) ?></p>
        <?php else: ?>
            <p>There is no page at this address.</p>
        <?php endif; ?>
        <p><a class="btn btn-primary" href="/attractions">Browse attractions</a></p>
    </div>
</div>
