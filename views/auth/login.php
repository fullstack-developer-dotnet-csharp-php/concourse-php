<?php use App\Support\Csrf; use App\Support\Config; ?>
<div class="container page-head"><h1>Sign in</h1></div>
<div class="container narrow">
    <form class="card-form" method="post" action="/login">
        <?= Csrf::field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label>Email<input type="email" name="email" required autocomplete="email" autofocus></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
    <?php if (Config::bool('auth.google.enabled')): ?>
    <p class="muted small">or</p>
    <a class="btn btn-ghost btn-block" href="/auth/google?next=<?= e(rawurlencode($next)) ?>">Sign in with Google</a>
    <?php endif; ?>
    <p class="muted">New here? <a href="/register?next=<?= e(rawurlencode($next)) ?>">Create an account</a></p>
</div>
