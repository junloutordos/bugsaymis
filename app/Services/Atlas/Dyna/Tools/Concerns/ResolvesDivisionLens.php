<?php

namespace App\Services\Atlas\Dyna\Tools\Concerns;

use App\Models\Division;
use App\Models\User;

trait ResolvesDivisionLens
{
    /**
     * Mirrors ExecutiveDashboardController::resolveLens() exactly — a Division Chief is
     * locked to their own division regardless of what $requestedDivisionId asks for;
     * OCD/Administrator get campus-wide, optionally narrowed by $requestedDivisionId.
     */
    private function resolveDivisionId(User $user, ?int $requestedDivisionId): ?int
    {
        $ownDivisionId = Division::where('division_chief_id', $user->id)->value('id');
        $isCampusLens = $user->isSuperAdmin() || $user->hasRole('OCD') || ! $ownDivisionId;

        return $isCampusLens ? $requestedDivisionId : $ownDivisionId;
    }
}
