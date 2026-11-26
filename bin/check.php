<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Support\SystemCheck;

$rows = SystemCheck::run(false);
foreach ($rows as $r) {
    $mark = $r['ok'] === true ? 'OK' : ($r['ok'] === false ? 'FAIL' : 'INFO');
    echo str_pad($mark, 6) . str_pad($r['label'], 38) . $r['detail'] . ($r['fix'] !== '' ? '  -> ' . $r['fix'] : '') . PHP_EOL;
}
$fail = SystemCheck::failures($rows);
echo PHP_EOL . ($fail === 0 ? 'Ready.' : $fail . ' check(s) failed.') . PHP_EOL;
exit($fail === 0 ? 0 : 1);
