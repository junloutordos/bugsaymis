<?php

namespace App\Console\Commands;

use App\Models\FacultyLoading\ResearchAdvisory;
use App\Services\FacultyLoading\ResearchGroupResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off, idempotent backfill: populate research_advisories.research_group_id
 * for rows created before the research_groups entity existed, deduping
 * co-advisers on the same (term, grade, title) into a single group.
 */
class BackfillResearchGroups extends Command
{
    protected $signature = 'research-groups:backfill {--dry-run : Report matches without writing}';

    protected $description = 'Link legacy research_advisories rows to research_groups by term+grade+title';

    public function handle(ResearchGroupResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = ResearchAdvisory::whereNull('research_group_id')->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to backfill — all research advisories already have a research_group_id.');
            return self::SUCCESS;
        }

        // Explicit begin/rollback (not DB::transaction()'s closure form) because
        // a dry-run must roll back ResearchGroupResolver's own writes too — it
        // creates a ResearchGroup as a side effect of resolve() regardless of
        // dry-run, so skipping only the advisory update here isn't enough.
        DB::beginTransaction();

        $linked = 0;
        foreach ($rows as $row) {
            $group = $resolver->resolve($row->academic_term_id, $row->grade_level, $row->research_title, $row->research_type);
            $this->line(($dryRun ? '[dry-run] ' : '')."Advisory #{$row->id} (\"{$row->research_title}\", Grade {$row->grade_level}) → group #{$group->id}");

            if (! $dryRun) {
                $row->update(['research_group_id' => $group->id]);
            }
            $linked++;
        }

        if ($dryRun) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        $this->newLine();
        $this->info("Linked: {$linked} / {$rows->count()}".($dryRun ? ' (dry-run — nothing written)' : ''));

        return self::SUCCESS;
    }
}
