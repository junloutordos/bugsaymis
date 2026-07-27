<?php

namespace App\Console\Commands\ClassRecord;

use App\Models\ClassRecord\ClassRecordStudent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResequenceStudents extends Command
{
    protected $signature = 'class-record:resequence-students';

    protected $description = 'One-off backfill: re-number every class record roster\'s sequence_number alphabetically by family name, then given name.';

    public function handle(): int
    {
        $quarterIds = ClassRecordStudent::where('is_active', true)
            ->distinct()
            ->pluck('class_record_quarter_id');

        $updated = 0;

        foreach ($quarterIds as $quarterId) {
            $students = ClassRecordStudent::where('class_record_quarter_id', $quarterId)
                ->where('is_active', true)
                ->orderBy('family_name')
                ->orderBy('given_name')
                ->get(['id', 'sequence_number']);

            DB::transaction(function () use ($students, &$updated) {
                foreach ($students as $i => $student) {
                    $newSequence = $i + 1;
                    if ($student->sequence_number !== $newSequence) {
                        $student->update(['sequence_number' => $newSequence]);
                        $updated++;
                    }
                }
            });
        }

        $this->info("Resequenced {$updated} student row(s) across " . $quarterIds->count() . ' roster(s).');

        return self::SUCCESS;
    }
}
