<?php

namespace App\Services\PerformanceManagementV2;

use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\PM2\EmployeeIpcrV2;
use App\Services\PerformanceManagement\WorkDistributionPlanClassifier;

class CoreFunctionGeneratorV2
{
    public function __construct(private WorkDistributionPlanClassifier $classifier = new WorkDistributionPlanClassifier())
    {
    }

    public function generate(EmployeeIpcrV2 $ipcr): int
    {
        $ipcr->loadMissing('user');
        $currentSyId = SchoolYear::where('is_current', true)->value('id');

        $assignments = LoadAssignment::with('subject')
            ->where('user_id', $ipcr->user_id)
            ->where('assignment_type', 'teaching')
            ->when($currentSyId, fn ($q) => $q->where('school_year_id', $currentSyId))
            ->get();

        $existingPlanIds = $ipcr->rows()->whereNotNull('plan_id')->pluck('plan_id');
        $created = 0;

        foreach ($assignments->groupBy('subject_id') as $group) {
            $representative = $group->sortBy('id')->first();
            $plan = $this->classifier->defaultPlanForLoadAssignment($representative, $ipcr->ratingPeriod?->year);

            if ($existingPlanIds->contains($plan->id)) {
                continue;
            }

            $subjectName = $representative->subject?->name ?? 'Teaching load';
            $units = number_format((float) $group->sum('load_units'), 2);

            $ipcr->rows()->create([
                'function_type'      => $representative->hasUnitLoad() ? 'core' : 'support',
                'plan_id'            => $plan->id,
                'individual_target'  => "{$subjectName} ({$units} u)",
            ]);
            $created++;
        }

        return $created;
    }
}
