<?php

namespace App\Services\HR;

use App\Models\HR\ActingAsSession;
use App\Models\HR\Substitution;

class ActingAsService
{
    public function forceEndForSubstitution(Substitution $substitution, string $reason): void
    {
        ActingAsSession::where('substitution_id', $substitution->id)
            ->open()
            ->update(['ended_at' => now(), 'ended_reason' => $reason]);
    }
}
