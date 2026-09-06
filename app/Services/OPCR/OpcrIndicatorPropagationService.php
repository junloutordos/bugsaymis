<?php

namespace App\Services\OPCR;

use App\Models\IPCRRatingPeriod;
use App\Models\OPCR\OpcrIndicator;
use App\Models\PerformanceIndicator;

class OpcrIndicatorPropagationService
{
    // Keeps an OpcrIndicator in sync with a Performance Indicator tagged to a
    // genuine PSHS Program (agencyOutcome.function_type = "Strategic Functions").
    // Core/Support Function PIs (individual employee targets) never propagate.
    // Resolves to the top-level Program when the PI is tagged to a child outcome,
    // since OPCR's own Program field only ever holds top-level rows.
    public function syncFromPerformanceIndicator(PerformanceIndicator $performanceIndicator): void
    {
        $performanceIndicator->loadMissing('agencyOutcome.parent', 'divisions');

        $outcome = $performanceIndicator->agencyOutcome;
        $program = $outcome?->parent_id ? $outcome->parent : $outcome;

        $existing = OpcrIndicator::where('performance_indicator_id', $performanceIndicator->id)->first();

        if (! $program?->isStrategicProgram()) {
            if ($existing) {
                $existing->update(['performance_indicator_id' => null]);
            }

            return;
        }

        $attributes = [
            'agency_outcome_id' => $program->id,
            'description' => $performanceIndicator->description,
            'target' => $performanceIndicator->target,
            'budget' => $performanceIndicator->budget,
        ];

        if ($existing) {
            $existing->update($attributes);
            $existing->divisions()->sync($performanceIndicator->divisions->pluck('id'));

            return;
        }

        $indicator = OpcrIndicator::create($attributes + [
            'fiscal_year' => $performanceIndicator->fiscal_year
                ?? IPCRRatingPeriod::current()->value('year')
                ?? (int) now()->format('Y'),
            'performance_indicator_id' => $performanceIndicator->id,
        ]);
        $indicator->divisions()->sync($performanceIndicator->divisions->pluck('id'));
    }

    public function unlinkFromPerformanceIndicator(PerformanceIndicator $performanceIndicator): void
    {
        OpcrIndicator::where('performance_indicator_id', $performanceIndicator->id)
            ->update(['performance_indicator_id' => null]);
    }
}
