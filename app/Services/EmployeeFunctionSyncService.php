<?php

namespace App\Services;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeFunctionSyncService
{
    /**
     * Sync this faculty member's Core Function rows from their current-term
     * Load Assignments. Grouping unit is subject_id (teaching) or
     * designation_id (admin/research/committee-with-load), mirroring the
     * subject-level grouping already proven correct in v1's
     * FacultyIPCRBaselineService.
     */
    public function syncFromFacultyLoading(User $user): void
    {
        $term = AcademicTerm::where('is_current', true)->first();
        if (! $term) {
            return;
        }

        DB::transaction(function () use ($user, $term) {
            $assignments = LoadAssignment::where('user_id', $user->id)
                ->where('academic_term_id', $term->id)
                ->get();

            $totalUnits = (float) $assignments->sum('load_units');
            $groups = $this->groupAssignments($assignments);

            $keptIds = [];

            foreach ($groups as $group) {
                $weight = $totalUnits > 0 ? round(($group['units'] / $totalUnits) * 100, 2) : 0;

                $row = EmployeeFunction::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'academic_term_id' => $term->id,
                        'source_type' => EmployeeFunction::SOURCE_LOAD_ASSIGNMENT,
                        'load_assignment_id' => $group['representative_assignment_id'],
                    ],
                    [
                        'function_type' => EmployeeFunction::TYPE_CORE,
                        'label' => $group['label'],
                        'weight_percent' => $weight,
                        'created_by' => $user->id,
                    ]
                );

                $keptIds[] = $row->id;
            }

            // Detach any prior auto-synced row for this user/term no longer
            // represented — UNLESS it has real IPCR V2 accomplishment data
            // logged against it, in which case it's left for manual review
            // (never silently drop rated/logged work).
            EmployeeFunction::where('user_id', $user->id)
                ->where('academic_term_id', $term->id)
                ->autoSynced()
                ->whereNotIn('id', $keptIds)
                ->whereDoesntHave('ipcrV2CoreItems', fn ($q) => $q->whereNotNull('actual_accomplishment')->where('actual_accomplishment', '!=', ''))
                ->delete();
        });
    }

    /**
     * @return array<int, array{label: string, units: float, representative_assignment_id: int}>
     *         keyed by a stable group key (subject_id or designation_id) so
     *         re-syncing finds the same group across runs.
     */
    private function groupAssignments($assignments): array
    {
        $groups = [];

        foreach ($assignments as $assignment) {
            if ($assignment->assignment_type === 'teaching' && $assignment->subject_id) {
                $key = 'subject_' . $assignment->subject_id;
                $groups[$key]['label'] ??= $assignment->subject?->name ?? 'Teaching Load';
                $groups[$key]['units'] = ($groups[$key]['units'] ?? 0) + (float) $assignment->load_units;
                $groups[$key]['representative_assignment_id'] ??= $assignment->id;

                continue;
            }

            if ($assignment->designation_id && (float) $assignment->load_units > 0) {
                $key = 'designation_' . $assignment->designation_id;
                $groups[$key]['label'] ??= $assignment->description ?? ($assignment->designation?->name ?? 'Designation with Load');
                $groups[$key]['units'] = ($groups[$key]['units'] ?? 0) + (float) $assignment->load_units;
                $groups[$key]['representative_assignment_id'] ??= $assignment->id;
            }
        }

        return $groups;
    }
}
