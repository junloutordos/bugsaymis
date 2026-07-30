<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\Office;
use App\Models\User;

/**
 * Read-only monitoring access for CID Chief and Academic Unit Heads — they can
 * view class record activity/status but never write to one. CID Chief sees
 * campus-wide; an AUH is scoped to their own unit via Office.unit_head ->
 * User.office_id, the same mechanism already used by
 * ClassScheduleController::scheduleCapability()'s 'unit' level. This
 * deliberately avoids users.academic_unit_id / subjects.academic_unit_id,
 * which are declared in the schema but never populated by any UI in this app.
 */
class ClassRecordMonitorScopeService
{
    public function isCidChiefOrAdmin(User $user): bool
    {
        return $user->hasPermission('class-records.admin') || $user->hasRole('CID Chief');
    }

    public function isUnitHead(User $user): bool
    {
        return Office::where('unit_head', $user->id)->exists();
    }

    public function canMonitor(User $user): bool
    {
        return $this->isCidChiefOrAdmin($user) || $this->isUnitHead($user);
    }

    /**
     * Faculty IDs this monitor may view. Null means unrestricted (CID Chief /
     * admin) — everyone else gets an explicit (possibly empty) list.
     *
     * @return array<int>|null
     */
    public function facultyIdsInScope(User $user): ?array
    {
        if ($this->isCidChiefOrAdmin($user)) {
            return null;
        }

        $officeIds = Office::where('unit_head', $user->id)->pluck('id');
        if ($officeIds->isEmpty()) {
            return [];
        }

        return User::whereIn('office_id', $officeIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Can this monitor view (read-only) the given class record? Checks EVERY
     * teacher on the record (the primary teacher_id plus any PEHM co-teacher
     * pivot rows) — an AUH whose unit covers any one of the record's
     * teachers can view the whole shared record, not just their own unit's
     * slice of it.
     */
    public function canView(User $user, ClassRecord $classRecord): bool
    {
        if (! $this->canMonitor($user)) {
            return false;
        }

        $facultyIds = $this->facultyIdsInScope($user);
        if ($facultyIds === null) {
            return true;
        }

        return ! empty(array_intersect($classRecord->allTeacherIds(), $facultyIds));
    }
}
