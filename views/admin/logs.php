<?php echo App\Support\View::partial('admin_nav'); ?>
<?php
use App\Support\Csrf;
use App\Support\LogReader;

$size = static fn(int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024, 1) . ' KB';
$url = static fn(array $over = []): string => '/admin/logs?' . http_build_query(array_filter(array_merge(['file' => $file, 'level' => $level, 'q' => $q, 'request' => $request, 'limit' => $limit], $over), static fn($v) => $v !== '' && $v !== null));
?>
<div class="container page-head">
    <h1>Logs</h1>
    <p class="muted">Application log files from <code>storage/logs</code>. Sensitive fields are masked when written.</p>
</div>
<div class="container">
    <?php if ($deleted): ?><div class="notice ok" role="status">Log file deleted.</div><?php endif; ?>
    <?php if ($files === []): ?>
        <div class="notice">No log files exist yet.</div>
    <?php else: ?>
        <form method="get" action="/admin/logs" class="log-filter">
            <select name="file" aria-label="Log file">
                <?php foreach ($files as $f): ?><option value="<?= e($f['name']) ?>"<?= $f['name'] === $file ? ' selected' : '' ?>><?= e($f['name']) ?> (<?= e($size($f['size'])) ?>)</option><?php endforeach; ?>
            </select>
            <select name="level" aria-label="Minimum level">
                <option value="">All levels</option>
                <?php foreach (LogReader::LEVELS as $l): ?><option value="<?= e($l) ?>"<?= $l === $level ? ' selected' : '' ?>><?= e($l) ?> and above</option><?php endforeach; ?>
            </select>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search text">
            <input type="text" name="request" value="<?= e($request) ?>" placeholder="Request id" size="10">
            <select name="limit" aria-label="Rows">
                <?php foreach ([100, 200, 500, 1000] as $n): ?><option value="<?= $n ?>"<?= $n === $limit ? ' selected' : '' ?>><?= $n ?> rows</option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-ghost" href="/admin/logs?file=<?= e(rawurlencode($file)) ?>">Reset</a>
        </form>
        <p class="log-counts">
            <?php foreach ($counts as $l => $n): ?><a class="badge log-<?= e(strtolower($l)) ?>" href="<?= e($url(['level' => $l])) ?>"><?= e($l) ?> <?= number_format($n) ?></a> <?php endforeach; ?>
        </p>
        <p class="muted">Showing <?= number_format(count($result['rows'])) ?> of <?= number_format($result['matched']) ?> matching lines (<?= number_format($result['total']) ?> in file), newest first.</p>
        <div class="table-wrap">
            <table class="table log-table">
                <thead><tr><th>Time (UTC)</th><th>Level</th><th>Request</th><th>Message</th></tr></thead>
                <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                    <tr class="log-row log-row-<?= e(strtolower($row['level'])) ?>">
                        <td><?= e(substr($row['time'], 11, 12)) ?></td>
                        <td><span class="badge log-<?= e(strtolower($row['level'])) ?>"><?= e($row['level']) ?></span></td>
                        <td><a href="<?= e('/admin/logs?' . http_build_query(['file' => $file, 'request' => $row['request']])) ?>"><?= e($row['request']) ?></a></td>
                        <td class="log-msg">
                            <?= e($row['message']) ?>
                            <?php if ($row['context'] !== ''): ?><details><summary>Details</summary><pre><?= e($row['context']) ?></pre></details><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($result['rows'] === []): ?><tr><td colspan="4">No lines match.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="settings-actions">
            <a class="btn btn-ghost" href="/admin/logs/<?= e(rawurlencode($file)) ?>">Download <?= e($file) ?></a>
            <form method="post" action="/admin/logs/<?= e(rawurlencode($file)) ?>/delete" data-confirm="Delete <?= e($file) ?>? This cannot be undone." style="display:inline">
                <?= Csrf::field() ?><button class="btn btn-ghost" type="submit">Delete this file</button>
            </form>
        </div>
    <?php endif; ?>
</div>
