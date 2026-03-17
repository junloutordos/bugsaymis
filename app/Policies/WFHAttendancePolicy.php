<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WFHAttendance;

class WFHAttendancePolicy extends GenericPolicy
{
    /**
     * Any authenticated user with wfh.time-in permission may create a record.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['wfh.time-in', 'wfh.view']);
    }

    /**
     * Owner always allowed. Monitors need wfh.monitor + org-scope match.
     */
    public function view(User $user, WFHAttendance $attendance): bool
    {
        if ($user->id === $attendance->user_id) {
            return true;
        }

        if (! $user->hasPermission('wfh.monitor')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->hasPermission('hr.employees.manage')) {
            return true;
        }

        $subject = $attendance->user;

        if ($user->hasRole('DivisionChief') && $subject?->division_id === $user->division_id) {
            return true;
        }

        if ($user->hasRole('OCD') && $subject?->office_id === $user->office_id) {
            return true;
        }

        return false;
    }

    /**
     * Only the record owner may update (time-out on their own record).
     */
    public function update(User $user, WFHAttendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }

    /**
     * Owner or users with hr.employees.manage (HR/Admin) may delete.
     */
    public function delete(User $user, WFHAttendance $attendance): bool
    {
        return $user->id === $attendance->user_id
            || $user->hasPermission('hr.employees.manage');
    }

    /**
     * Users with wfh.monitor permission may access the monitoring view.
     */
    public function monitor(User $user): bool
    {
        return $user->hasPermission('wfh.monitor');
    }
}
