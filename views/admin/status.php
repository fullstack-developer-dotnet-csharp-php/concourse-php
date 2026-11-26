<?php echo App\Support\View::partial('admin_nav'); ?>
<?php use App\Support\Csrf; ?>
<div class="container page-head">
    <h1>System check</h1>
    <p class="muted">Shows whether this server can run the site and what still needs to be done.</p>
</div>
<div class="container">
    <table class="status-table">
        <thead><tr><th>Check</th><th>Result</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['label']) ?></td>
                <td><span class="pill <?= $r['ok'] === true ? 'pill-ok' : ($r['ok'] === false ? 'pill-bad' : 'pill-info') ?>"><?= $r['ok'] === true ? 'OK' : ($r['ok'] === false ? 'Fix' : 'Info') ?></span></td>
                <td><?= e($r['detail']) ?><?php if ($r['fix'] !== ''): ?> <span class="muted"><?= e($r['fix']) ?></span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <section class="settings-group">
        <h2>Catalog sync</h2>
        <p class="help">Loads attractions from the FunEx API in small steps, so it works on hosts with a short time limit. Keep this page open until it says finished.</p>
        <p><button type="button" class="btn btn-primary" id="run-sync">Run sync</button> <span class="test-result" id="sync-result" role="status"></span></p>
        <input type="hidden" id="csrf-token" value="<?= e(Csrf::token()) ?>">
    </section>
</div>
<script>
(function () {
    var btn = document.getElementById('run-sync');
    var out = document.getElementById('sync-result');
    var token = document.getElementById('csrf-token').value;
    btn.addEventListener('click', function () {
        btn.disabled = true;
        out.textContent = 'Starting...';
        function step() {
            var body = new URLSearchParams({ _csrf: token });
            fetch('/admin/sync', { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) { out.textContent = 'Failed: ' + d.message; btn.disabled = false; return; }
                    out.textContent = (d.finished ? 'Finished. ' : 'Working... ') + d.stored + ' of ' + d.apiTotal + ' attractions stored.';
                    if (d.finished) { btn.disabled = false; return; }
                    step();
                })
                .catch(function (e) { out.textContent = 'Failed: ' + e.message; btn.disabled = false; });
        }
        step();
    });
})();
</script>
