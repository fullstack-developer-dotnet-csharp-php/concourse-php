<?php use App\Support\Csrf; ?>
<div class="container page-head"><h1>Admin login</h1></div>
<div class="container narrow">
    <form class="card-form" method="post" action="/admin/login">
        <?= Csrf::field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label>Password<input type="password" name="password" required autocomplete="current-password" autofocus></label>
        <button class="btn btn-primary" type="submit">Log in</button>
    </form>
</div>
