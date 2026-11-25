<?php use App\Api\ApiException; ?>
<div class="container page-head">
    <h1>Something went wrong</h1>
</div>
<div class="container">
    <div class="error-box">
        <p><strong><?= e($message) ?></strong></p>
        <p class="muted">If this keeps happening, contact support and quote reference <strong><?= e($requestId) ?></strong>.</p>
        <?php if ($detail): ?>
            <h2>Technical details (admin only)</h2>
            <p><?= e(get_class($e)) ?>: <?= e($e->getMessage()) ?></p>
            <?php if ($e instanceof ApiException): ?>
                <dl class="facts">
                    <dt>HTTP status</dt><dd><?= e($e->status) ?></dd>
                    <dt>Error code</dt><dd><?= e($e->errorCode) ?></dd>
                    <dt>URL</dt><dd><?= e($e->url) ?></dd>
                    <?php if ($e->body !== ''): ?><dt>Response body</dt><dd><pre><?= e($e->body) ?></pre></dd><?php endif; ?>
                </dl>
            <?php endif; ?>
            <pre><?= e($e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) ?></pre>
            <p><a href="/admin/logs?request=<?= e($requestId) ?>">Open this request in the logs</a></p>
        <?php endif; ?>
        <p><a class="btn btn-primary" href="/">Back to home</a></p>
    </div>
</div>
