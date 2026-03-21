<?php

namespace App\Policies\Recruitment;

use App\Models\Placement;
use App\Models\User;
use App\Policies\GenericPolicy;

class PlacementPolicy extends GenericPolicy
{
    public function approve(User $user): bool
    {
        return $this->can($user, 'recruitment.approve');
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'recruitment.view');
    }

    public function manageOnboarding(User $user, Placement $placement): bool
    {
        return $this->can($user, 'recruitment.onboarding');
    }
}
