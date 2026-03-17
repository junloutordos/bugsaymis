<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WFHAccomplishment;

class WFHAccomplishmentPolicy
{
    /**
     * Any authenticated user may create an accomplishment
     * (service layer enforces the time-in guard).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * A user may view their own accomplishment.
     * Unit Heads may view accomplishments of users in their office/unit.
     * Division Chiefs may view accomplishments of users in their division.
     * HR Directors may view all.
     */
    public function view(User $user, WFHAccomplishment $accomplishment): bool
    {
        if ($user->id === $accomplishment->user_id) {
            return true;
        }

        if ($user->hasAnyRole(['HR', 'Administrator'])) {
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
     * Only the record owner may delete their accomplishment.
     */
    public function delete(User $user, WFHAccomplishment $accomplishment): bool
    {
        return $user->id === $accomplishment->user_id;
    }
}
