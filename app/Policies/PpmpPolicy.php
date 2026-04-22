<?php

namespace App\Policies;

use App\Models\PPMP\Ppmp;
use App\Models\User;

class PpmpPolicy extends GenericPolicy
{
    /**
     * Super admin bypass.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['ppmp.create', 'ppmp.view_all']);
    }

    public function view(User $user, Ppmp $ppmp): bool
    {
        if ($user->hasPermission('ppmp.view_all')) {
            return true;
        }

        return $user->division_id === $ppmp->division_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('ppmp.create');
    }

    public function update(User $user, Ppmp $ppmp): bool
    {
        if (!$user->hasPermission('ppmp.create')) {
            return false;
        }

        if (!$ppmp->canEdit()) {
            return false;
        }

        return $user->division_id === $ppmp->division_id;
    }

    public function delete(User $user, Ppmp $ppmp): bool
    {
        if (!$user->hasPermission('ppmp.create')) {
            return false;
        }

        if ($ppmp->status !== Ppmp::STATUS_DRAFT) {
            return false;
        }

        return $user->division_id === $ppmp->division_id;
    }

    public function submit(User $user, Ppmp $ppmp): bool
    {
        if (!$user->hasPermission('ppmp.submit')) {
            return false;
        }

        if (!$ppmp->canSubmit()) {
            return false;
        }

        return $user->division_id === $ppmp->division_id;
    }

    public function approve(User $user, Ppmp $ppmp): bool
    {
        if (!$user->hasPermission('ppmp.approve')) {
            return false;
        }

        return $ppmp->canApprove();
    }

    public function returnForRevision(User $user, Ppmp $ppmp): bool
    {
        return $this->approve($user, $ppmp);
    }

    public function consolidate(User $user): bool
    {
        return $user->hasPermission('ppmp.consolidate');
    }

    public function export(User $user, Ppmp $ppmp): bool
    {
        if (!$user->hasPermission('ppmp.export')) {
            return false;
        }

        if ($user->hasPermission('ppmp.view_all')) {
            return true;
        }

        return $user->division_id === $ppmp->division_id;
    }
}
