<?php
// Usage: php scripts/dump_attendance_clean.php --badge=51 --date=2026-01-05
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$opts = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', substr($arg, 2), 2);
        $opts[$parts[0]] = $parts[1] ?? null;
    }
}

$badge = $opts['badge'] ?? null;
$date = $opts['date'] ?? null;
if (! $badge || ! $date) {
    fwrite(STDERR, "Usage: php scripts/dump_attendance_clean.php --badge=51 --date=YYYY-MM-DD\n");
    exit(1);
}

$row = DB::table('attendance_clean')->where('BadgeNumber', $badge)->where('AttDate', $date)->first();
if (! $row) {
    echo "No row found for Badge $badge on $date\n";
    exit(0);
}

echo json_encode($row, JSON_PRETTY_PRINT) . "\n";

return 0;
