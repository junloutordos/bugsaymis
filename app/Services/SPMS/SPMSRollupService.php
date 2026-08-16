<?php

namespace App\Services\SPMS;

use App\Models\SPMS\Dpcr;
use App\Models\SPMS\Ipcr;

class SPMSRollupService
{
    public function rollupIpcrsToDpcr(Dpcr $dpcr): ?float
    {
        $ipcrs = Ipcr::with('targets')
            ->whereNotNull('rated_at')
            ->where('fiscal_period_id', $dpcr->fiscal_period_id)
            ->whereHas('user', fn ($query) => $query->where('division_id', $dpcr->division_id))
            ->get();

        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($ipcrs as $ipcr) {
            if ($ipcr->dpcr_id === null) {
                $ipcr->update(['dpcr_id' => $dpcr->id]);
            }

            $coreTargets = $ipcr->targets->where('function_type', 'core')->whereNotNull('rating_avg');
            if ($coreTargets->isEmpty()) {
                continue;
            }

            $coreAverage = (float) $coreTargets->avg('rating_avg');
            $coreWeight = (float) $coreTargets->sum('weight_pct');
            if ($coreWeight <= 0.0) {
                $coreWeight = 1.0;
            }

            $weightedSum += $coreAverage * $coreWeight;
            $weightTotal += $coreWeight;
        }

        if ($weightTotal <= 0.0) {
            return null;
        }

        return round($weightedSum / $weightTotal, 2);
    }
}
