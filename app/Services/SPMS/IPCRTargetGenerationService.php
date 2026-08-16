<?php

namespace App\Services\SPMS;

use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;

class IPCRTargetGenerationService
{
    public function generate(Ipcr $ipcr): array
    {
        $schoolYearId = $ipcr->fiscalPeriod->school_year_id
            ?? SchoolYear::where('is_current', true)->value('id');

        if (!$schoolYearId) {
            return ['attached' => 0, 'personalized' => 0];
        }

        $assignments = LoadAssignment::where('user_id', $ipcr->user_id)
            ->where('school_year_id', $schoolYearId)
            ->get()
            ->groupBy('assignment_type');

        $existing = $ipcr->targets()
            ->where('source_type', LoadAssignment::class)
            ->pluck('source_id')
            ->all();

        $attached = 0;

        foreach ($assignments as $assignmentType => $group) {
            $totalUnits = (float) $group->sum('load_units') ?: 1;

            foreach ($group as $assignment) {
                if (in_array($assignment->id, $existing, true)) {
                    continue;
                }

                IpcrTarget::create([
                    'ipcr_id' => $ipcr->id,
                    'function_type' => 'core',
                    'source_type' => LoadAssignment::class,
                    'source_id' => $assignment->id,
                    'success_indicator' => $this->buildTarget($assignment, $assignmentType),
                    'weight_pct' => round(((float) $assignment->load_units ?: 1) / $totalUnits * 100, 2),
                ]);

                $attached++;
            }
        }

        return ['attached' => $attached, 'personalized' => 0];
    }

    private function buildTarget(LoadAssignment $assignment, string $assignmentType): string
    {
        return sprintf(
            '%s: %s',
            ucfirst($assignmentType),
            $assignment->description ?? ucfirst($assignmentType)
        );
    }
}
