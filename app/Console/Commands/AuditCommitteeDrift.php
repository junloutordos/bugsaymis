<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAccomplishment;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use Illuminate\Console\Command;

/**
 * Read-only report of committee data that drifted before the roster/rating
 * unification shipped (see CommitteeRosterService / CommitteeRatingService).
 * Never writes anything.
 *
 * Roster drift for a given committee self-heals the next time that
 * committee is saved in either the DM or PMS catalog editor (or via an
 * explicit "Sync to Term"); this report exists so you can see what's still
 * stale rather than waiting to stumble on it.
 *
 * Rating/accomplishment drift is historical: it can only have been created
 * by the old dual-write paths and won't recur now that both modules share
 * CommitteeRatingService, but existing mismatches need a manual call on
 * which value is correct — this command does not pick a winner.
 */
class AuditCommitteeDrift extends Command
{
    protected $signature = 'committees:audit-drift {--term_id= : Academic term to check roster drift for (default: current)}';
    protected $description = 'Report committee roster and rating drift left over from before roster/rating unification (read-only)';

    public function handle(): int
    {
        $termId = $this->option('term_id') ?: AcademicTerm::where('is_current', true)->value('id');

        if (! $termId) {
            $this->warn('No academic term marked current and none given via --term_id — skipping roster drift check.');
        } else {
            $this->auditRoster((int) $termId);
        }

        $this->newLine();
        $this->auditRatings();

        return self::SUCCESS;
    }

    private function auditRoster(int $termId): void
    {
        $this->info("── Roster drift (term #{$termId}) ──");

        $rows = [];

        Committee::with(['members', 'subCommittees.members'])
            ->whereNull('parent_committee_id')
            ->get()
            ->each(function (Committee $committee) use ($termId, &$rows) {
                $this->compareRoster($committee, $termId, $rows);
                foreach ($committee->subCommittees as $sub) {
                    $this->compareRoster($sub, $termId, $rows);
                }
            });

        if (empty($rows)) {
            $this->line('No roster drift found.');
            return;
        }

        $this->table(['Committee', 'User ID', 'Issue'], $rows);
    }

    private function compareRoster(Committee $committee, int $termId, array &$rows): void
    {
        $rosterIds = $committee->members->pluck('id')->all();
        if ($committee->head_id) {
            $rosterIds[] = $committee->head_id;
        }
        $rosterIds = array_values(array_unique($rosterIds));

        $activeAssignments = FacultyCommitteeAssignment::where('committee_id', $committee->id)
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->get();

        $assignedIds = $activeAssignments->pluck('user_id')->all();

        foreach (array_diff($rosterIds, $assignedIds) as $userId) {
            $rows[] = [$committee->name, $userId, 'On DM roster, no active FL assignment this term'];
        }
        foreach (array_diff($assignedIds, $rosterIds) as $userId) {
            $rows[] = [$committee->name, $userId, 'Active FL assignment, not on DM roster'];
        }

        $chairAssignment = $activeAssignments->firstWhere('role', 'chairperson');
        if ($chairAssignment && $chairAssignment->user_id !== $committee->head_id) {
            $rows[] = [$committee->name, $chairAssignment->user_id, "Assignment role=chairperson but DM head_id is {$committee->head_id}"];
        }
    }

    private function auditRatings(): void
    {
        $this->info('── Accomplishment/rating drift (FL table vs IPCR pivot) ──');

        $rows = [];

        FacultyCommitteeAccomplishment::with('assignment')->get()->each(function (FacultyCommitteeAccomplishment $acc) use (&$rows) {
            $assignment = $acc->assignment;
            if (! $assignment) return;

            $ipcr = EmployeeIPCR::where('user_id', $assignment->user_id)
                ->whereHas('plans', fn ($q) => $q->where('work_distribution_plans.id', $acc->work_distribution_plan_id))
                ->with(['plans' => fn ($q) => $q->where('work_distribution_plans.id', $acc->work_distribution_plan_id)])
                ->when($acc->rating_period_id, fn ($q, $pid) => $q->where('rating_period_id', $pid))
                ->latest()
                ->first();

            $pivot = $ipcr?->plans->first()?->pivot;
            if (! $pivot) return;

            $fields = ['accomplishment', 'mov_link', 'sup_quality', 'sup_efficiency', 'sup_timeliness', 'sup_average'];
            $diffs = [];
            foreach ($fields as $field) {
                $a = $acc->{$field};
                $b = $pivot->{$field};
                if ($a !== null && $b !== null && (string) $a !== (string) $b) {
                    $diffs[] = "{$field}: FL='{$a}' vs IPCR='{$b}'";
                }
            }

            if ($diffs) {
                $rows[] = [$assignment->user_id, $acc->work_distribution_plan_id, $acc->rating_period_id ?? '—', implode('; ', $diffs)];
            }
        });

        if (empty($rows)) {
            $this->line('No rating/accomplishment drift found.');
            return;
        }

        $this->table(['User ID', 'Plan ID', 'Period ID', 'Diffs'], $rows);
    }
}
