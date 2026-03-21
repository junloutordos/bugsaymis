<?php

namespace App\Policies\Recruitment;

use App\Models\Application;
use App\Models\User;
use App\Policies\GenericPolicy;

class EvaluationPolicy extends GenericPolicy
{
    public function score(User $user, Application $application): bool
    {
        return $this->can($user, 'recruitment.evaluate');
    }

    public function manageInterview(User $user, Application $application): bool
    {
        return $this->can($user, 'recruitment.evaluate');
    }

    public function rank(User $user): bool
    {
        return $this->can($user, 'recruitment.rank');
    }
}
