<?php

// Usage:
// php scripts/compute_tardiness.php --badge=51 --date=2026-01-05 --time=08:30:01 --attType=0

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$opts = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', substr($arg, 2), 2);
        $opts[$parts[0]] = $parts[1] ?? null;
    }
}

$badge = $opts['badge'] ?? $argv[1] ?? null;
$attDate = $opts['date'] ?? null;
$attTime = $opts['time'] ?? null;
$attType = isset($opts['attType']) ? (string)$opts['attType'] : (isset($argv[4]) ? (string)$argv[4] : null);

if (! $badge || ! $attDate || ! $attTime || $attType === null) {
    fwrite(STDERR, "Usage: php scripts/compute_tardiness.php --badge=51 --date=YYYY-MM-DD --time=HH:MM:SS --attType=0\n");
    exit(1);
}

echo "Badge: $badge, Date: $attDate, Time: $attTime, attType: $attType\n";

try {
    $db = DB::connection();
    $dbName = $db->getDatabaseName();
    echo "DB: $dbName\n";
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// find schedule for badge
$schedule = DB::table('schedule')->where('badgeNumber', $badge)->first();
if (! $schedule) {
    echo "No schedule found for badge $badge\n";
    exit(0);
}

$dayName = (new DateTime($attDate))->format('l');
$dayMap = [
    'Monday' => 'm',
    'Tuesday' => 't',
    'Wednesday' => 'w',
    'Thursday' => 'th',
    'Friday' => 'f',
    'Saturday' => 'sat',
    'Sunday' => 'sun',
];
$prefix = $dayMap[$dayName] ?? null;
if (! $prefix) {
    echo "Unable to map day $dayName to schedule prefix\n";
    exit(1);
}

// normalize attType: allow numeric or letters (I/O/P etc.)
$norm = function ($t) {
    $t = (string)$t;
    $map = [
        'I' => '0', 'i' => '0', 'IN' => '0', 'in' => '0',
        'O' => '3', 'o' => '3', 'OUT' => '3', 'out' => '3',
        'P' => '1', 'p' => '1',
    ];
    if (isset($map[$t])) return $map[$t];
    return preg_match('/^\d+$/', $t) ? $t : null;
};

$attTypeNorm = $norm($attType);
if ($attTypeNorm === null) {
    echo "Unrecognized attType: $attType\n";
    exit(1);
}

$schedCol = null;
switch ($attTypeNorm) {
    case '0': $schedCol = $prefix . '_timein'; break;
    case '1': $schedCol = $prefix . '_breakout'; break;
    case '2': $schedCol = $prefix . '_breakin'; break;
    case '3': $schedCol = $prefix . '_timeout'; break;
}

if (! $schedCol) {
    echo "No schedule column mapped for attType $attTypeNorm\n";
    exit(1);
}

if (! isset($schedule->{$schedCol}) || ! $schedule->{$schedCol}) {
    echo "Schedule column $schedCol is empty for badge $badge\n";
    exit(0);
}

$schedTime = $schedule->{$schedCol};
echo "Schedule ($schedCol): $schedTime\n";

    try {
        $schedDT = Carbon::parse($attDate . ' ' . $schedTime);
        $attDT = Carbon::parse($attDate . ' ' . $attTime);
        $diffSeconds = $attDT->getTimestamp() - $schedDT->getTimestamp();
        $diff = $diffSeconds / 60.0;
        echo "Computed diffInMinutes (att - sched): $diff\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Time parse error: " . $e->getMessage() . "\n");
        exit(1);
    }

$tardinessVal = null;
$undertimeVal = null;
if (in_array($attTypeNorm, ['0','2'])) {
    if ($diff > 0) $tardinessVal = $diff;
}
if (in_array($attTypeNorm, ['1','3'])) {
    if ($diff < 0) $undertimeVal = abs($diff);
}

echo "Tardiness: " . ($tardinessVal === null ? 'null' : $tardinessVal) . ", Undertime: " . ($undertimeVal === null ? 'null' : $undertimeVal) . "\n";

// update attendance_clean
$tardMap = ['0' => 'startTime1_tardiness', '2' => 'startTime3_tardiness'];
$underMap = ['1' => 'startTime2_undertime', '3' => 'startTime4_undertime'];

$updateData = ['updated_at' => now()];
if (isset($tardMap[$attTypeNorm]) && $tardinessVal !== null) $updateData[$tardMap[$attTypeNorm]] = $tardinessVal;
if (isset($underMap[$attTypeNorm]) && $undertimeVal !== null) $updateData[$underMap[$attTypeNorm]] = $undertimeVal;

if (count($updateData) === 1) {
    echo "No values to update in attendance_clean. Nothing to do.\n";
    exit(0);
}

$updated = DB::table('attendance_clean')
    ->where('BadgeNumber', $badge)
    ->where('AttDate', $attDate)
    ->update($updateData);

if ($updated) {
    echo "Updated attendance_clean for Badge $badge, Date $attDate.\n";
} else {
    // insert a minimal row
    $insert = [
        'BadgeNumber' => $badge,
        'AttDate' => $attDate,
        'created_at' => now(),
        'updated_at' => now(),
    ];
    if (isset($tardMap[$attTypeNorm]) && isset($tardinessVal) && $tardinessVal !== null) $insert[$tardMap[$attTypeNorm]] = $tardinessVal;
    if (isset($underMap[$attTypeNorm]) && isset($undertimeVal) && $undertimeVal !== null) $insert[$underMap[$attTypeNorm]] = $undertimeVal;

    DB::table('attendance_clean')->insert($insert);
    echo "Inserted attendance_clean for Badge $badge, Date $attDate.\n";
}

echo "Done.\n";

return 0;
