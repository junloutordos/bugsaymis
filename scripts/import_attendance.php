<?php

// Usage: php scripts/import_attendance.php /path/to/file.dat
// If no path provided, defaults to ~/Downloads/4890194560200_attlog.dat

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Accepts: php scripts/import_attendance.php /path/to/file.dat [table_name]
$filepath = $argv[1] ?? null;
$table = $argv[2] ?? 'attendance';
if (! $filepath) {
    $home = getenv('HOME') ?: __DIR__ . '/..';
    $filepath = $home . '/Downloads/4890194560200_attlog.dat';
}

if (! file_exists($filepath)) {
    fwrite(STDERR, "File not found: $filepath\n");
    fwrite(STDERR, "Usage: php scripts/import_attendance.php /path/to/file.dat [table_name]\n");
    exit(1);
}

echo "Importing file: $filepath\n";

$handle = fopen($filepath, 'r');
if (! $handle) {
    fwrite(STDERR, "Unable to open file: $filepath\n");
    exit(1);
}

$inserted = 0;
$skipped = 0;

// Verify DB connection early
try {
    $dbName = DB::connection()->getDatabaseName();
    echo "DB connected: $dbName (target table: $table)\n";
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

while (($cols = fgetcsv($handle, 0, "\t")) !== false) {
    // Skip empty lines
    if (count($cols) === 1 && trim($cols[0]) === '') {
        $skipped++;
        continue;
    }

    $badge = isset($cols[0]) ? trim($cols[0]) : null;
    $attDate = isset($cols[1]) ? trim($cols[1]) : null;
    $attTime = isset($cols[2]) ? trim($cols[2]) : null;
    $attType = isset($cols[3]) ? trim($cols[3]) : null;

    if (! $badge) {
        $skipped++;
        continue;
    }

    try {
        if ($table === 'library_attendances') {
            $scannedAt = null;
            if ($attDate && $attTime) {
                $scannedAt = date('Y-m-d H:i:s', strtotime($attDate . ' ' . $attTime));
            } elseif ($attDate) {
                $scannedAt = date('Y-m-d H:i:s', strtotime($attDate));
            }

            DB::table('library_attendances')->insertOrIgnore([
                'pisay_systemid' => $badge,
                'student_id' => null,
                'student_name' => null,
                'scanned_at' => $scannedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table($table)->insertOrIgnore([
                'BadgeNumber' => $badge,
                'AttDate' => $attDate,
                'AttTime' => $attTime,
                'attType' => $attType,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $inserted++;
    } catch (Throwable $e) {
        fwrite(STDERR, "Insert failed for badge $badge: " . $e->getMessage() . "\n");
        $skipped++;
        continue;
    }
}

fclose($handle);

echo "Done. Inserted: $inserted, Skipped: $skipped\n";

return 0;
