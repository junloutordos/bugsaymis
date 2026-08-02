<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\ClassRecord\ClassRecord;
use App\Models\User;

class GetClassRecordComplianceTool implements DynaTool
{
    public function name(): string { return 'get_class_record_compliance'; }

    public function description(): string
    {
        return 'Returns class record status distribution (draft/submitted/checked counts). '
             . 'Use for questions about grade submission compliance or class record completion.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(User $user, array $input): array
    {
        $query = ClassRecord::query();

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->whereHas('teacher', fn ($q) => $q->where('division_id', $user->division_id));
        }

        return $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}
