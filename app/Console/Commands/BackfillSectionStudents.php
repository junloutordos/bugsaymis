<?php

namespace App\Console\Commands;

use App\Models\Registrar\StudentEnrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillSectionStudents extends Command
{
    protected $signature = 'backfill:section_students';
    protected $description = 'Sync the legacy section_students mirror table from student_enrollments for students whose section assignment never got mirrored';

    public function handle()
    {
        $this->info('Scanning student enrollments with an assigned section...');

        $enrollments = StudentEnrollment::query()
            ->whereNotNull('section_id')
            ->with('section:id,syid,levelid')
            ->get(['id', 'student_id', 'section_id']);

        $synced = 0;
        $skipped = 0;

        foreach ($enrollments as $enrollment) {
            $section = $enrollment->section;
            if (! $section || $section->syid === null) {
                $skipped++;
                continue;
            }

            $existing = DB::table('section_students')
                ->where('studentid', $enrollment->student_id)
                ->where('syid', $section->syid)
                ->first();

            if ($existing && (int) $existing->sectionid === (int) $section->id) {
                $skipped++;
                continue;
            }

            DB::table('section_students')->updateOrInsert(
                ['studentid' => $enrollment->student_id, 'syid' => $section->syid],
                ['sectionid' => $section->id, 'levelid' => $section->levelid],
            );
            $synced++;
        }

        $this->info("Backfill completed. Synced: {$synced}, already up to date: {$skipped}.");
        return 0;
    }
}
