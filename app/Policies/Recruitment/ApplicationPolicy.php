<?php

namespace App\Policies\Recruitment;

use App\Models\Application;
use App\Models\User;
use App\Policies\GenericPolicy;

class ApplicationPolicy extends GenericPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'recruitment.view');
    }

    public function view(User $user, Application $application): bool
    {
        return $this->can($user, 'recruitment.view');
    }

    public function create(User $user): bool
    {
        // HR staff or self-service (applicant-facing)
        return $this->can($user, 'recruitment.apply');
    }

    public function advance(User $user, Application $application): bool
    {
        return $this->can($user, 'recruitment.evaluate');
    }

    public function reject(User $user, Application $application): bool
    {
        return $this->can($user, 'recruitment.evaluate');
    }

    public function withdraw(User $user, Application $application): bool
    {
        // Applicant can withdraw their own; HR can withdraw any
        return $this->owns($user, $application, 'applicant_id')
            || $this->can($user, 'recruitment.manage');
    }
}
