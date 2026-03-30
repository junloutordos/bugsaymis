<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMessengerialSequences extends Command
{
    protected $signature = 'backfill:messengerial_sequences';
    protected $description = 'Backfill messengerial_sequences table based on existing messengerial_requests';

    public function handle()
    {
        $this->info('Scanning existing messengerial requests...');

        $map = []; // map[prefix][year] = maxNumber

        $rows = DB::table('messengerial_requests')->select('id','reference_no','unit','created_at')->cursor();
        foreach ($rows as $r) {
            $prefix = null;
            $year = null;
            $num = 0;

            if (!empty($r->reference_no)) {
                // expected format: PREFIX-YYYY-MM-XXXX
                if (preg_match('/^([A-Z]+)-(\d{4})-(\d{2})-(\d{3,})$/', $r->reference_no, $m)) {
                    $prefix = $m[1];
                    $year = (int)$m[2];
                    $num = (int)$m[4];
                }
            }

            if (!$prefix) {
                // derive from unit
                $unit = $r->unit ?? '';
                $unitNorm = strtolower(trim($unit));
                if ($unitNorm !== '' && str_contains($unitNorm, 'finance') && str_contains($unitNorm, 'administr')) {
                    $prefix = 'FAD';
                } elseif (strtoupper($unit) === 'FAD') {
                    $prefix = 'FAD';
                } else {
                    $words = preg_split('/\s+/', $unit);
                    $abbr = '';
                    foreach ($words as $w) if (strlen($w) > 0) $abbr .= strtoupper($w[0]);
                    $prefix = $abbr ?: 'GEN';
                }
            }

            if (!$year) {
                $year = $r->created_at ? (int)date('Y', strtotime($r->created_at)) : (int)date('Y');
            }

            if (!isset($map[$prefix])) $map[$prefix] = [];
            if (!isset($map[$prefix][$year])) $map[$prefix][$year] = 0;
            if ($num > $map[$prefix][$year]) $map[$prefix][$year] = $num;
        }

        $this->info('Preparing sequences...');
        foreach ($map as $prefix => $years) {
            foreach ($years as $year => $maxNumber) {
                // baseline: FAD should start at 5 so next will be 6; others baseline 0
                $baseline = ($prefix === 'FAD') ? 5 : 0;
                $lastNumber = max($maxNumber, $baseline);

                $existing = DB::table('messengerial_sequences')
                    ->where('division_code', $prefix)
                    ->where('year', $year)
                    ->first();

                if ($existing) {
                    if ($existing->last_number < $lastNumber) {
                        DB::table('messengerial_sequences')->where('id', $existing->id)->update(['last_number' => $lastNumber, 'updated_at' => now()]);
                        $this->info("Updated sequence for {$prefix} {$year} -> {$lastNumber}");
                    } else {
                        $this->info("Skipping {$prefix} {$year}, existing {$existing->last_number} >= computed {$lastNumber}");
                    }
                } else {
                    DB::table('messengerial_sequences')->insert([
                        'division_code' => $prefix,
                        'year' => $year,
                        'last_number' => $lastNumber,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->info("Inserted sequence for {$prefix} {$year} -> {$lastNumber}");
                }
            }
        }

        $this->info('Backfill completed.');
        return 0;
    }
}
