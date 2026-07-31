<?php

namespace App\Services\ALP;

use App\Models\ALP\AlpProgramCycle;
use App\Models\User;

class AlpAccessService
{
    public function canView(User $user, AlpProgramCycle $cycle): bool
    {
        return $user->isSuperAdmin()
            || $user->hasAnyPermission(['alp.manage', 'alp.coordinate', 'alp.registrar-certify', 'alp.approve', 'alp.reports', 'alp.audit'])
            || (int) $cycle->coordinator_id === (int) $user->id
            || (int) $cycle->adviser_id === (int) $user->id;
    }

    public function canManage(User $user, AlpProgramCycle $cycle): bool
    {
        return $user->isSuperAdmin()
            || $user->hasAnyPermission(['alp.manage', 'alp.coordinate'])
            || (int) $cycle->coordinator_id === (int) $user->id
            || ($user->hasPermission('alp.advise') && (int) $cycle->adviser_id === (int) $user->id);
    }

    public function authorizeView(User $user, AlpProgramCycle $cycle): void
    {
        abort_unless($this->canView($user, $cycle), 403);
    }

    public function authorizeManage(User $user, AlpProgramCycle $cycle): void
    {
        abort_unless($this->canManage($user, $cycle), 403);
    }
}
