<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WFHAccomplishment;

class WFHAccomplishmentPolicy extends GenericPolicy
{
    /**
     * Any user with wfh.accomplishments.create permission may add accomplishments.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('wfh.accomplishments.create');
    }

    /**
     * Owner always allowed. Monitors need wfh.monitor + org-scope match.
     */
    public function view(User $user, WFHAccomplishment $accomplishment): bool
    {
        if ($user->id === $accomplishment->user_id) {
            return true;
        }

        if (! $user->hasPermission('wfh.monitor')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->hasPermission('hr.employees.manage')) {
            return true;
        }

        $subject = $accomplishment->user;

        if ($user->hasRole('DivisionChief') && $subject?->division_id === $user->division_id) {
            return true;
        }

        if ($user->hasRole('OCD') && $subject?->office_id === $user->office_id) {
            return true;
        }

        return false;
    }

    /**
     * Only the record owner may update their accomplishment.
     */
    public function update(User $user, WFHAccomplishment $accomplishment): bool
    {
        return $user->id === $accomplishment->user_id;
    }

    /**
     * Owner or users with wfh.accomplishments.delete may delete.
     */
    public function delete(User $user, WFHAccomplishment $accomplishment): bool
    {
        return $user->id === $accomplishment->user_id
            || $user->hasPermission('wfh.accomplishments.delete');
    }
}
