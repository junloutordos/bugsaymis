<?php

namespace App\Http\Controllers;

use App\Models\EmployeeIPCR;
use Inertia\Inertia;

class PMTIPCRController extends Controller
{
    /**
     * Normalize function type string to canonical name.
     */
    private function normalizeFunctionType(?string $raw): string
    {
        if (!$raw) return 'Uncategorized';
        $t = strtolower(trim($raw));
        if (in_array($t, ['strategic', 'strategic functions', 'strategic function'])) return 'Strategic Functions';
        if (in_array($t, ['core', 'core functions', 'core function']))               return 'Core Functions';
        if (in_array($t, ['support', 'support functions', 'support function']))      return 'Support Functions';
        return trim($raw);
    }

    /**
     * Compute weighted final IPCR rating (Strategic 30%, Core 55%, Support 15%).
     * Mirrors the formula used in DivisionChiefIPCRShow.vue and PMTIPCRShow.vue.
     */
    private function computeWeightedAverage($plans): ?float
    {
        $weights = ['Strategic Functions' => 0.30, 'Core Functions' => 0.55, 'Support Functions' => 0.15];
        $groups  = [];

        foreach ($plans as $plan) {
            $ft     = $this->normalizeFunctionType($plan->performance_indicator?->agencyOutcome?->function_type);
            $supAvg = $plan->pivot?->sup_average;
            if ($supAvg === null || $supAvg === '') continue;
            $groups[$ft]['total'] = ($groups[$ft]['total'] ?? 0) + (float) $supAvg;
            $groups[$ft]['count'] = ($groups[$ft]['count'] ?? 0) + 1;
        }

        $totalWeighted = 0;
        $hasAny        = false;
        foreach ($groups as $ft => $data) {
            $weight         = $weights[$ft] ?? 0;
            $totalWeighted += ($data['total'] / $data['count']) * $weight;
            $hasAny         = true;
        }

        return $hasAny ? round($totalWeighted, 2) : null;
    }

    /**
     * List all IPCRs submitted to PMT for review.
     */
    public function index()
    {
        $ipcrs = EmployeeIPCR::with([
                'user.division',
                'plans.performance_indicator.agencyOutcome',
            ])
            ->whereIn('status', ['Submitted to PMT', 'PMT Returned for Revision', 'Approved by PMT'])
            ->orderBy('submitted_for_pmtreview_at', 'desc')
            ->get()
            ->map(function ($ipcr) {
                $ipcr->overall_average = $this->computeWeightedAverage($ipcr->plans);
                return $ipcr;
            });

        return Inertia::render('PerformanceManagement/PMTIPCRIndex', [
            'ipcrs' => $ipcrs,
        ]);
    }

    /**
     * Show a single IPCR for PMT review (read-only).
     */
    public function show($id)
    {
        $ipcr = EmployeeIPCR::with([
            'user.division.divisionchief',
            'plans.performance_indicator.agencyOutcome',
        ])->findOrFail($id);

        return Inertia::render('PerformanceManagement/PMTIPCRShow', [
            'ipcr'       => $ipcr,
            'plans'      => $ipcr->plans,
            'employee'   => $ipcr->user,
            'supervisor' => $ipcr->user?->division?->divisionchief,
        ]);
    }

    /**
     * PMT approves the IPCR.
     */
    public function approve(EmployeeIPCR $employeeIPCR)
    {
        $employeeIPCR->update([
            'status' => 'Approved by PMT',
        ]);

        return to_route('pmt-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR approved by PMT.');
    }

    /**
     * PMT returns the IPCR for revision — Division Chief will handle the return to employee.
     */
    public function returnForRevision(EmployeeIPCR $employeeIPCR)
    {
        $employeeIPCR->update([
            'status' => 'PMT Returned for Revision',
        ]);

        return to_route('pmt-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR returned for revision. Division Chief will be notified.');
    }
}
