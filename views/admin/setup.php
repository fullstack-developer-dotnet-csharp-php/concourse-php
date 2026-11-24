<?php use App\Support\Csrf; ?>
<div class="container page-head"><h1>Admin setup</h1><p class="muted">Choose the admin password for this site. This page works only until a password exists.</p></div>
<div class="container narrow">
    <div class="notice">To prove you control the server, open the file <code><?= e($tokenPath) ?></code> (by FTP or the hosting file manager) and paste its contents below. The file is deleted when setup finishes.</div>
    <form class="card-form" method="post" action="/admin/setup">
        <?= Csrf::field() ?>
        <label>Setup token<input name="token" required autocomplete="off" autofocus></label>
        <label>New admin password (at least 10 characters)<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <button class="btn btn-primary" type="submit">Create admin password</button>
    </form>
</div>
