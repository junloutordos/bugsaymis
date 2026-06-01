<?php

namespace App\Http\Controllers;

use App\Mail\IPCRApprovedByPMTMail;
use App\Mail\IPCRDirectorSignedMail;
use App\Mail\IPCRPMTReturnedMail;
use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Mail;
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
            ->whereIn('status', ['Submitted to PMT', 'PMT Returned for Revision', 'Approved by PMT', 'Director Signed'])
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
            'isOCD'      => auth()->user()->hasPermission('ipcr.approve'),
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

        User::havingRole('OCD')->each(function ($ocd) use ($employeeIPCR) {
            Mail::to($ocd->email)->send(new IPCRApprovedByPMTMail($employeeIPCR, $ocd->name));
        });

        AuditLogger::log([
            'action'         => 'ipcr_pmt_approved',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $employeeIPCR->id,
            'new_values'     => ['status' => 'Approved by PMT'],
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

        $employeeIPCR->load('user.division');

        // Notify Division Chief
        $divisionId = $employeeIPCR->user->division_id;
        if ($divisionId) {
            $chiefId = Division::where('id', $divisionId)->value('division_chief_id');
            $dc = $chiefId ? User::find($chiefId) : null;
            if ($dc) {
                Mail::to($dc->email)->send(new IPCRPMTReturnedMail($employeeIPCR, $dc->name));
            }
        }

        // Notify Employee
        Mail::to($employeeIPCR->user->email)->send(new IPCRPMTReturnedMail($employeeIPCR, $employeeIPCR->user->name));

        AuditLogger::log([
            'action'         => 'ipcr_pmt_returned',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $employeeIPCR->id,
            'new_values'     => ['status' => 'PMT Returned for Revision'],
        ]);

        return to_route('pmt-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR returned for revision. Division Chief and employee have been notified.');
    }

    public function directorSign(EmployeeIPCR $employeeIPCR)
    {
        abort_unless(auth()->user()->hasPermission('ipcr.approve'), 403);

        $user = auth()->user();
        $employeeIPCR->update([
            'status'             => 'Director Signed',
            'director_signed_at' => now(),
            'director_signature' => $user->electronic_signature,
        ]);

        // Notify DC
        $divisionId = $employeeIPCR->user->division_id;
        if ($divisionId) {
            $chiefId = Division::where('id', $divisionId)->value('division_chief_id');
            $dc = $chiefId ? User::find($chiefId) : null;
            if ($dc) {
                Mail::to($dc->email)->send(new IPCRDirectorSignedMail($employeeIPCR, $dc->name));
            }
        }

        // Notify Employee
        Mail::to($employeeIPCR->user->email)->send(new IPCRDirectorSignedMail($employeeIPCR, $employeeIPCR->user->name));

        AuditLogger::log([
            'action'         => 'ipcr_director_signed',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $employeeIPCR->id,
            'new_values'     => ['status' => 'Director Signed'],
        ]);

        return to_route('pmt-ipcr.show', $employeeIPCR->id)->with('success', 'IPCR signed by Director.');
    }
}
