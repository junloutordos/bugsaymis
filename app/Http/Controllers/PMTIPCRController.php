<?php

namespace App\Http\Controllers;

use App\Mail\IPCRApprovedByPMTMail;
use App\Mail\IPCRDirectorSignedMail;
use App\Mail\IPCRPMTReturnedMail;
use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\User;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class PMTIPCRController extends Controller
{
    public function __construct(private IPCRWorkflowService $workflow)
    {
    }

    /**
     * List all IPCRs submitted to PMT for review.
     */
    public function index()
    {
        $ipcrs = EmployeeIPCR::with([
                'user.division',
                'period',
                'plans.performance_indicator.agencyOutcome',
            ])
            ->whereIn('status', ['Submitted to PMT', 'PMT Returned for Revision', 'Approved by PMT', 'Director Signed'])
            ->orderBy('submitted_for_pmtreview_at', 'desc')
            ->get()
            ->map(function ($ipcr) {
                $ipcr->overall_average = $ipcr->final_numeric_rating
                    ?? $this->workflow->computeWeightedAverage($ipcr->plans);
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
            'period',
            'plans.performance_indicator.agencyOutcome',
        ])->findOrFail($id);

        $ocdUser = User::havingRole('OCD')->first();

        return Inertia::render('PerformanceManagement/PMTIPCRShow', [
            'ipcr'       => $ipcr,
            'plans'      => $ipcr->plans,
            'employee'   => $ipcr->user,
            'supervisor' => $ipcr->user?->division?->divisionchief,
            'ocdUser'    => $ocdUser ? $ocdUser->only('name', 'position') : null,
            'isOCD'      => auth()->user()->hasPermission('ipcr.approve'),
            'isMutable'  => $ipcr->isMutable(),
            'periodClosed' => $ipcr->isPeriodClosed(),
        ]);
    }

    /**
     * PMT approves the IPCR.
     */
    public function approve(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_PMT_APPROVED, [], 'ipcr_pmt_approved');

        User::havingRole('OCD')->each(function ($ocd) use ($employeeIPCR) {
            Mail::to($ocd->email)->send(new IPCRApprovedByPMTMail($employeeIPCR, $ocd->name));
        });

        return to_route('pmt-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR approved by PMT.');
    }

    /**
     * PMT returns the IPCR for revision — Division Chief will handle the return to employee.
     */
    public function returnForRevision(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_PMT_RETURNED, [], 'ipcr_pmt_returned');

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

        return to_route('pmt-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR returned for revision. Division Chief and employee have been notified.');
    }

    public function directorSign(EmployeeIPCR $employeeIPCR)
    {
        abort_unless(auth()->user()->hasPermission('ipcr.approve'), 403);

        // Terminal transition: signs, then snapshots the final numeric + adjectival rating
        $this->workflow->finalize($employeeIPCR, auth()->user());

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

        return to_route('pmt-ipcr.show', $employeeIPCR->id)->with('success', 'IPCR signed by Director.');
    }
}
