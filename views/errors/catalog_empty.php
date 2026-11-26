<div class="container page-head">
    <h1>We are getting ready</h1>
</div>
<div class="container">
    <div class="error-box">
        <p>The ticket catalog has not been loaded yet. Please come back in a few minutes.</p>
        <?php if ($admin): ?>
            <p><a class="btn btn-primary" href="/admin/status">Open the System page to load the catalog</a></p>
        <?php else: ?>
            <p class="muted">Site owner: sign in at /admin, open System and press Run sync.</p>
        <?php endif; ?>
    </div>
</div>
