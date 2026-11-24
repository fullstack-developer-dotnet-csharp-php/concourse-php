<?php use App\Api\ApiException; ?>
<div class="container page-head">
    <h1>Something went wrong</h1>
    <p class="muted"><?= e(get_class($e)) ?></p>
</div>
<div class="container">
    <div class="error-box">
        <p><strong><?= e($e->getMessage()) ?></strong></p>
        <?php if ($e instanceof ApiException): ?>
            <dl class="facts">
                <dt>HTTP status</dt><dd><?= e($e->status) ?></dd>
                <dt>Error code</dt><dd><?= e($e->errorCode) ?></dd>
                <dt>URL</dt><dd><?= e($e->url) ?></dd>
                <?php if ($e->retryAfter !== null): ?><dt>Retry after</dt><dd><?= e($e->retryAfter) ?> seconds</dd><?php endif; ?>
                <?php if ($e->body !== ''): ?><dt>Response body</dt><dd><pre><?= e($e->body) ?></pre></dd><?php endif; ?>
            </dl>
        <?php endif; ?>
        <?php if ($debug): ?>
            <h2>Stack trace</h2>
            <pre><?= e($e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) ?></pre>
        <?php endif; ?>
        <p><a class="btn btn-primary" href="/">Back to home</a></p>
    </div>
</div>
