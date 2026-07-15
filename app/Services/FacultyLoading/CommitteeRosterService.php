<?php

namespace App\Services\FacultyLoading;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps a committee's per-term faculty_committee_assignments rows in sync
 * with the Data Management roster (committees.members()/head_id) so
 * Faculty Loading and Performance Management always operate on the same
 * current roster, instead of drifting apart via the old manual,
 * additive-only "Sync to Term" button.
 */
class CommitteeRosterService
{
    public function __construct(private readonly LoadComputationService $loads) {}

    /**
     * Reconcile one committee (and its sub-committees) for a given term:
     * create assignments for anyone newly on the DM roster, deactivate
     * (never delete — preserves rating/accomplishment history) assignments
     * for anyone no longer on it, and keep the chairperson role in sync
     * with committees.head_id. Skips faculty whose load record is locked
     * for the term, matching the safety rail FL's own manual edits use.
     */
    public function reconcileCommitteeRoster(Committee $committee, int $schoolYearId, int $termId): array
    {
        $committee->loadMissing(['members', 'subCommittees.members']);

        $result = ['created' => 0, 'deactivated' => 0, 'role_updated' => 0, 'skipped_locked' => 0];

        $this->reconcileOne($committee, $schoolYearId, $termId, $result);

        foreach ($committee->subCommittees as $sub) {
            $this->reconcileOne($sub, $schoolYearId, $termId, $result);
        }

        return $result;
    }

    /**
     * Convenience wrapper called right after a committee's DM roster is
     * edited (from either the DM or PMS catalog editor — both write to the
     * same committees/committee_user rows): reconciles the *current*
     * academic term only. No-ops quietly if no term is marked current —
     * a committee save must never fail because of that.
     */
    public function reconcileCurrentTerm(Committee $committee): void
    {
        $currentTerm = AcademicTerm::where('is_current', true)->first();
        if (! $currentTerm) return;

        $this->reconcileCommitteeRoster($committee, $currentTerm->school_year_id, $currentTerm->id);
    }

    private function reconcileOne(Committee $committee, int $schoolYearId, int $termId, array &$result): void
    {
        $rosterUserIds = $committee->members->pluck('id')->all();
        if ($committee->head_id) {
            $rosterUserIds[] = $committee->head_id;
        }
        $rosterUserIds = array_values(array_unique($rosterUserIds));

        if ($committee->head_id) {
            $this->createIfMissing($committee, $committee->head_id, 'chairperson', $schoolYearId, $termId, $result);
        }
        foreach ($committee->members as $member) {
            if ($member->id === $committee->head_id) continue;
            $this->createIfMissing($committee, $member->id, 'member', $schoolYearId, $termId, $result);
        }

        // Deactivate anyone with an active assignment who is no longer on the roster.
        FacultyCommitteeAssignment::where('committee_id', $committee->id)
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->whereNotIn('user_id', $rosterUserIds)
            ->get()
            ->each(function (FacultyCommitteeAssignment $assignment) use (&$result) {
                if ($this->isLocked($assignment->user_id, $assignment->academic_term_id)) {
                    $result['skipped_locked']++;
                    return;
                }
                $this->deactivate($assignment);
                $result['deactivated']++;
            });

        // Keep the chairperson role in sync with committees.head_id — only
        // touches the single 'chairperson' seat; co_chair/secretary are
        // FL-only concepts the DM catalog doesn't model, left untouched.
        FacultyCommitteeAssignment::where('committee_id', $committee->id)
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->whereIn('user_id', $rosterUserIds)
            ->get()
            ->each(function (FacultyCommitteeAssignment $assignment) use ($committee, &$result) {
                $shouldBeChair = $assignment->user_id === $committee->head_id;
                $isChairRole   = $assignment->role === 'chairperson';

                if ($shouldBeChair && ! $isChairRole && $assignment->role !== 'co_chair') {
                    $this->updateRole($assignment, $committee, 'chairperson', $result);
                } elseif (! $shouldBeChair && $isChairRole) {
                    $this->updateRole($assignment, $committee, 'member', $result);
                }
            });
    }

    private function createIfMissing(Committee $committee, int $userId, string $role, int $schoolYearId, int $termId, array &$result): void
    {
        $exists = FacultyCommitteeAssignment::where('user_id', $userId)
            ->where('academic_term_id', $termId)
            ->where('committee_id', $committee->id)
            ->where('status', 'active')
            ->exists();

        if ($exists) return;

        if ($this->isLocked($userId, $termId)) {
            $result['skipped_locked']++;
            return;
        }

        $loadUnits = $committee->loadUnitsFor($role);
        $load      = $this->loads->findOrCreateFacultyLoad($userId, $schoolYearId, $termId);

        $la = LoadAssignment::create([
            'faculty_load_id'  => $load->id,
            'user_id'          => $userId,
            'school_year_id'   => $schoolYearId,
            'academic_term_id' => $termId,
            'assignment_type'  => 'committee',
            'load_units'       => $loadUnits,
            'description'      => "{$committee->name} ({$role})",
            'created_by'       => Auth::id(),
        ]);

        FacultyCommitteeAssignment::create([
            'user_id'            => $userId,
            'school_year_id'     => $schoolYearId,
            'academic_term_id'   => $termId,
            'committee_id'       => $committee->id,
            'committee_name'     => $committee->name,
            'role'               => $role,
            'load_units'         => $loadUnits,
            'status'             => 'active',
            'load_assignment_id' => $la->id,
        ]);

        $this->loads->syncLoad($load);
        $result['created']++;
    }

    private function updateRole(FacultyCommitteeAssignment $assignment, Committee $committee, string $newRole, array &$result): void
    {
        if ($this->isLocked($assignment->user_id, $assignment->academic_term_id)) {
            $result['skipped_locked']++;
            return;
        }

        $loadUnits = $committee->loadUnitsFor($newRole);
        $assignment->update(['role' => $newRole, 'load_units' => $loadUnits]);

        if ($assignment->load_assignment_id) {
            LoadAssignment::where('id', $assignment->load_assignment_id)->update([
                'load_units'  => $loadUnits,
                'description' => "{$committee->name} ({$newRole})",
            ]);
        }

        $load = FacultyLoad::where('user_id', $assignment->user_id)
            ->where('academic_term_id', $assignment->academic_term_id)
            ->first();
        if ($load) {
            $this->loads->syncLoad($load);
        }

        $result['role_updated']++;
    }

    private function deactivate(FacultyCommitteeAssignment $assignment): void
    {
        $laId   = $assignment->load_assignment_id;
        $userId = $assignment->user_id;
        $termId = $assignment->academic_term_id;

        $assignment->update(['status' => 'inactive']);

        if ($laId) {
            LoadAssignment::destroy($laId);
        }

        $load = FacultyLoad::where('user_id', $userId)->where('academic_term_id', $termId)->first();
        if ($load) {
            $this->loads->syncLoad($load);
        }
    }

    private function isLocked(int $userId, int $termId): bool
    {
        return (bool) FacultyLoad::where('user_id', $userId)
            ->where('academic_term_id', $termId)
            ->value('is_locked');
    }
}
