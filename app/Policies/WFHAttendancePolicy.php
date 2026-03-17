<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WFHAttendance;

class WFHAttendancePolicy
{
    /**
     * Any authenticated user may time in / time out (create their own record).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * A user may view their own record.
     * Unit Heads may view records belonging to their office/unit.
     * Division Chiefs may view records belonging to their division.
     * HR Directors may view all records.
     */
    public function view(User $user, WFHAttendance $attendance): bool
    {
        if ($user->id === $attendance->user_id) {
            return true;
        }

        if ($user->hasAnyRole(['HR', 'Administrator'])) {
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
     * Only the record owner or HR Director may delete.
     */
    public function delete(User $user, WFHAttendance $attendance): bool
    {
        return $user->id === $attendance->user_id
            || $user->hasAnyRole(['HR', 'Administrator']);
    }

    /**
     * Unit Heads, Division Chiefs, and HR Directors may access the monitoring view.
     */
    public function monitor(User $user): bool
    {
        return $user->hasAnyRole(['DivisionChief', 'OCD', 'HR', 'Administrator']);
    }
}
