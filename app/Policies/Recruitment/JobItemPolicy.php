<?php

namespace App\Policies\Recruitment;

use App\Models\JobItem;
use App\Models\User;
use App\Policies\GenericPolicy;

class JobItemPolicy extends GenericPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'recruitment.view');
    }

    public function view(User $user, JobItem $item): bool
    {
        return $this->can($user, 'recruitment.view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'recruitment.manage');
    }

    public function update(User $user, JobItem $item): bool
    {
        // Only draft items can be edited freely
        if ($item->status !== 'draft') {
            return $this->can($user, 'recruitment.manage');
        }
        return $this->ownsOrCan($user, $item, 'recruitment.manage', 'created_by');
    }

    public function publish(User $user, JobItem $item): bool
    {
        return $this->can($user, 'recruitment.publish');
    }

    public function delete(User $user, JobItem $item): bool
    {
        return $this->can($user, 'recruitment.manage') && $item->status === 'draft';
    }
}
