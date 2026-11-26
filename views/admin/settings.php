<?php echo App\Support\View::partial('admin_nav'); ?>
<?php
use App\Support\Config;
use App\Support\Csrf;

$value = static function (string $path) use ($posted) {
    if ($posted !== null && array_key_exists($path, $posted)) {
        return $posted[$path];
    }
    return Config::get($path);
};
$tests = ['FunEx Concourse API' => 'api', 'Catalog storage' => 'store', 'Payments (Cashfree)' => 'cashfree'];
?>
<div class="container page-head">
    <h1>Settings</h1>
    <p class="muted">Every setting for this site is on this page. Values are saved to <code>storage/settings.json</code>.</p>
</div>
<div class="container narrow-wide">
    <?php if ($saved): ?><div class="notice ok" role="status">Settings saved.</div><?php endif; ?>
    <?php if ($errors): ?>
        <div class="notice error" role="alert"><strong>Nothing was saved.</strong>
            <ul><?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form method="post" action="/admin/settings" id="settings-form" autocomplete="off">
        <?= Csrf::field() ?>
        <?php foreach ($schema as $group => $fields): ?>
            <section class="settings-group">
                <h2><?= e($group) ?></h2>
                <?php foreach ($fields as $f): ?>
                    <?php [$path, $label, $type, $help] = $f; $id = 'f-' . str_replace('.', '-', $path); ?>
                    <div class="setting<?= isset($errors[$path]) ? ' has-error' : '' ?>">
                        <?php if ($type === 'bool'): ?>
                            <label class="check"><input type="checkbox" id="<?= e($id) ?>" name="s[<?= e($path) ?>]" value="1" <?= $value($path) ? 'checked' : '' ?>> <?= e($label) ?></label>
                        <?php else: ?>
                            <label for="<?= e($id) ?>"><?= e($label) ?></label>
                            <?php if ($type === 'select'): ?>
                                <select id="<?= e($id) ?>" name="s[<?= e($path) ?>]">
                                    <?php foreach ($f[4] as $k => $text): ?><option value="<?= e($k) ?>" <?= (string) $value($path) === (string) $k ? 'selected' : '' ?>><?= e($text) ?></option><?php endforeach; ?>
                                </select>
                            <?php elseif ($type === 'secret'): ?>
                                <input id="<?= e($id) ?>" type="password" name="s[<?= e($path) ?>]" value="" autocomplete="new-password" placeholder="<?= Config::string($path) !== '' ? 'Saved (leave blank to keep)' : 'Not set' ?>">
                            <?php elseif ($type === 'textarea'): ?>
                                <textarea id="<?= e($id) ?>" name="s[<?= e($path) ?>]" rows="10" maxlength="20000" placeholder="Leave blank to use the built-in template"><?= e($value($path)) ?></textarea>
                            <?php else: ?>
                                <input id="<?= e($id) ?>" type="<?= $type === 'int' ? 'number' : ($type === 'url' ? 'url' : 'text') ?>" name="s[<?= e($path) ?>]" value="<?= e($value($path)) ?>"<?= $type === 'int' ? 'number' : ($type === 'url' ? 'url' : 'text') ?>" name="s[<?= e($path) ?>]" value="<?= e($value($path)) ?>"<?= $type === 'int' ? ' min="' . e($f[4]) . '" max="' . e($f[5]) . '"' : '' ?>>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($help !== ''): ?><p class="help"><?= e($help) ?></p><?php endif; ?>
                        <?php if (isset($errors[$path])): ?><p class="field-error"><?= e($errors[$path]) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (isset($tests[$group])): ?>
                    <p><button type="button" class="btn btn-ghost" data-test="<?= e($tests[$group]) ?>">Test saved settings</button> <span class="test-result" data-result="<?= e($tests[$group]) ?>" role="status"></span></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
        <section class="settings-group">
            <h2>Admin</h2>
            <div class="setting">
                <label for="admin_password_new">New admin password</label>
                <input id="admin_password_new" type="password" name="admin_password_new" autocomplete="new-password" minlength="10" placeholder="Leave blank to keep the current password">
                <p class="help">At least 10 characters.</p>
            </div>
        </section>
        <div class="settings-actions">
            <button class="btn btn-primary" type="submit">Save settings</button>
        </div>
    </form>
    <form method="post" action="/admin/logout"><?= Csrf::field() ?><button class="btn btn-ghost" type="submit">Log out</button></form>
    <input type="hidden" id="csrf-token" value="<?= e(Csrf::token()) ?>">
</div>
