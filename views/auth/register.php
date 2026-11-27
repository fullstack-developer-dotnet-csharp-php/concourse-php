<?php use App\Support\Csrf; use App\Support\Config; ?>
<div class="container page-head"><h1>Create an account</h1><p class="muted">You need an account to book tickets. It only takes a moment.</p></div>
<div class="container narrow">
    <form class="card-form" method="post" action="/register">
        <?= Csrf::field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label>First name<input name="first" required autocomplete="given-name" autofocus></label>
        <label>Last name<input name="last" required autocomplete="family-name"></label>
        <label>Email<input type="email" name="email" required autocomplete="email"></label>
        <label>Password (at least 8 characters)<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>
    <?php if (Config::bool('auth.google.enabled')): ?>
    <p class="muted small">or</p>
    <a class="btn btn-ghost btn-block" href="/auth/google?next=<?= e(rawurlencode($next)) ?>">Sign in with Google</a>
    <?php endif; ?>
    <p class="muted">Already have an account? <a href="/login?next=<?= e(rawurlencode($next)) ?>">Sign in</a></p>
</div>
