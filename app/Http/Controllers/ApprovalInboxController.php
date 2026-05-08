<?php

namespace App\Http\Controllers;

use App\Services\ApprovalInboxService;
use App\Models\ITJobRequest;
use App\Models\VehicleRequest;
use App\Models\FacilityRequest;
use App\Models\WorkRequest;
use App\Models\ServiceRequest;
use App\Models\MessengerialRequest;
use App\Models\HR\LeaveApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ApprovalInboxController extends Controller
{
    private const VALID_TYPES = [
        'it_job_requests',
        'vehicle_requests',
        'facility_requests',
        'work_requests',
        'service_requests',
        'messengerial_requests',
        'gate_passes',
        'leave_applications',
    ];

    /**
     * GET /approvals
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $this->isApprover($user)) {
            abort(403, 'You do not have an approver role.');
        }

        $service = new ApprovalInboxService($user);
        $tabs    = $service->getPendingItems();

        $totalCount = array_sum(array_column($tabs, 'count'));

        $filters = [
            'search' => $request->query('search', ''),
        ];

        return Inertia::render('Approvals/Inbox', compact('tabs', 'totalCount', 'filters'));
    }

    /**
     * POST /approvals/{type}/{id}/approve
     */
    public function approve(Request $request, string $type, int $id)
    {
        if (! in_array($type, self::VALID_TYPES, true)) {
            abort(404, 'Invalid request type.');
        }

        $user = $request->user();

        try {
            [$model, $record] = $this->resolveRecord($type, $id);

            $this->authoriseApprove($user, $type, $record);
            $this->checkPending($type, $record);

            return $this->delegateApprove($request, $type, $record);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error('ApprovalInboxController::approve error', [
                'type'  => $type,
                'id'    => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * POST /approvals/{type}/{id}/decline
     */
    public function decline(Request $request, string $type, int $id)
    {
        if (! in_array($type, self::VALID_TYPES, true)) {
            abort(404, 'Invalid request type.');
        }

        $request->validate(['reason' => 'required|string|min:1|max:1000']);

        $user = $request->user();

        try {
            [$model, $record] = $this->resolveRecord($type, $id);

            $this->authoriseApprove($user, $type, $record);
            $this->checkPending($type, $record);

            return $this->delegateDecline($request, $type, $record);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error('ApprovalInboxController::decline error', [
                'type'  => $type,
                'id'    => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isApprover($user): bool
    {
        return $user->hasAnyRole(['Administrator', 'DivisionChief', 'GSU Head', 'OCD', 'FAD Chief'])
            || str_contains($user->position ?? '', 'FAD')
            || $user->hasPermission('hr.leave.approve');
    }

    /**
     * Resolve the record for the given type and id.
     * Returns [modelClass, record].
     * Aborts 404 if not found.
     */
    private function resolveRecord(string $type, int $id): array
    {
        switch ($type) {
            case 'it_job_requests':
                $record = ITJobRequest::find($id);
                if (! $record) abort(404);
                return [ITJobRequest::class, $record];

            case 'vehicle_requests':
                $record = VehicleRequest::find($id);
                if (! $record) abort(404);
                return [VehicleRequest::class, $record];

            case 'facility_requests':
                $record = FacilityRequest::find($id);
                if (! $record) abort(404);
                return [FacilityRequest::class, $record];

            case 'work_requests':
                $record = WorkRequest::find($id);
                if (! $record) abort(404);
                return [WorkRequest::class, $record];

            case 'service_requests':
                $record = ServiceRequest::find($id);
                if (! $record) abort(404);
                return [ServiceRequest::class, $record];

            case 'messengerial_requests':
                $record = MessengerialRequest::find($id);
                if (! $record) abort(404);
                return [MessengerialRequest::class, $record];

            case 'gate_passes':
                $record = DB::table('gatepass')->where('id', $id)->first();
                if (! $record) abort(404);
                return ['gatepass', $record];

            case 'leave_applications':
                $record = LeaveApplication::find($id);
                if (! $record) abort(404);
                return [LeaveApplication::class, $record];
        }

        abort(404);
    }

    /**
     * Verify the authenticated user is authorised to act on this record.
     */
    private function authoriseApprove($user, string $type, $record): void
    {
        $isDC    = $user->hasRole('DivisionChief') || $user->hasRole('Administrator');
        $isFAD   = str_contains($user->position ?? '', 'FAD') || $user->hasRole('FAD Chief') || $user->hasRole('Administrator');
        $isGSU   = $user->hasRole('GSU Head') || $user->hasRole('Administrator');
        $isOCD   = $user->hasRole('OCD') || $user->hasRole('Administrator');
        $isHR    = $user->hasPermission('hr.leave.approve') || $user->hasRole('Administrator');

        switch ($type) {
            case 'it_job_requests':
                if ($isDC && (int) $record->divisionchief_id === (int) $user->id) break;
                if ($isOCD) break;
                abort(403);

            case 'vehicle_requests':
                if ($isDC) {
                    $canAct = (int) ($record->division_chief_id ?? 0) === (int) $user->id;
                    if (! $canAct) {
                        $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                        $canAct = $divisionIds->isNotEmpty()
                            && \App\Models\User::where('id', $record->requestor_id)
                                ->whereIn('division_id', $divisionIds)->exists();
                    }
                    if ($canAct) break;
                }
                if ($isGSU || $isOCD) break;
                abort(403);

            case 'facility_requests':
                if ($isDC) {
                    $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                    $canAct = $divisionIds->isNotEmpty()
                        && \App\Models\User::where('id', $record->requestor_id)
                            ->whereIn('division_id', $divisionIds)->exists();
                    if ($canAct) break;
                }
                if ($isFAD || $isGSU || $isOCD) break;
                abort(403);

            case 'work_requests':
                if ($isDC) {
                    $canAct = (int) ($record->division_chief_id ?? 0) === (int) $user->id;
                    if (! $canAct) {
                        $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                        $canAct = $divisionIds->isNotEmpty()
                            && \App\Models\User::where('id', $record->requester_id)
                                ->whereIn('division_id', $divisionIds)->exists();
                    }
                    if ($canAct) break;
                }
                if ($isFAD) break;
                abort(403);

            case 'service_requests':
                if ($isDC) {
                    $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                    $canAct = $divisionIds->isNotEmpty()
                        && \App\Models\User::where('id', $record->requestor_id)
                            ->whereIn('division_id', $divisionIds)->exists();
                    if ($canAct) break;
                }
                if ($isFAD) break;
                abort(403);

            case 'messengerial_requests':
                if (($isDC || $isOCD) && (int) ($record->division_chief_id ?? 0) === (int) $user->id) break;
                if ($user->hasRole('Administrator')) break;
                abort(403);

            case 'gate_passes':
                if ($isDC) {
                    $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                    $requester = DB::table('users')
                        ->whereRaw('CAST(badge_id AS CHAR) = ?', [(string) $record->badgeNumber])
                        ->first();
                    if ($requester && in_array($requester->division_id, $divisionIds->toArray())) break;
                }
                if ($isOCD) break;
                abort(403);

            case 'leave_applications':
                if ($isHR) break;
                abort(403);
        }
    }

    /**
     * Check the record is still in a pending state. Return 409 if already acted upon.
     */
    private function checkPending(string $type, $record): void
    {
        $pendingStatuses = [
            'it_job_requests'      => ['Pending Division Chief Approval', 'Pending OCD Approval'],
            'vehicle_requests'     => ['Pending', 'Approved'],
            'facility_requests'    => ['Pending', 'Pending FAD Approval', 'Pending OCD Approval'],
            'work_requests'        => ['Pending', 'GSU Approved'],
            'service_requests'     => ['Pending', 'Approved'],
            'messengerial_requests'=> ['Pending Division Chief Approval', 'Approved'],
            'gate_passes'          => ['Pending', 'Division Approved'],
            'leave_applications'   => ['pending', 'hr_verified', 'forwarded'],
        ];

        $allowed = $pendingStatuses[$type] ?? [];
        $status  = $type === 'gate_passes' ? ($record->status ?? '') : ($record->status ?? '');

        if (! in_array($status, $allowed, true)) {
            abort(409, 'This request has already been acted upon.');
        }
    }

    /**
     * Delegate the approve action to the existing per-module controller method.
     * Uses the record's current status to determine which approval stage is being acted on.
     */
    private function delegateApprove(Request $request, string $type, $record)
    {
        $user   = $request->user();
        $status = $record->status ?? '';
        $isFAD  = str_contains($user->position ?? '', 'FAD');

        switch ($type) {
            case 'it_job_requests':
                if ($status === 'Pending Division Chief Approval') {
                    $request->merge(['action' => 'approve']);
                    return app(\App\Http\Controllers\ITJobRequestController::class)
                        ->approveByDivisionChief($request, $record);
                }
                $request->merge(['action' => 'approve']);
                return app(\App\Http\Controllers\ITJobRequestController::class)
                    ->approveByOCD($request, $record);

            case 'vehicle_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\VehicleRequestController::class)
                        ->approveInApp($request, $record);
                }
                $request->merge(['action' => 'approve']);
                return app(\App\Http\Controllers\VehicleRequestController::class)
                    ->approveByOCDInApp($request, $record);

            case 'facility_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\FacilityRequestController::class)
                        ->approveInApp($request, $record);
                }
                $request->merge(['action' => 'approve']);
                return app(\App\Http\Controllers\FacilityRequestController::class)
                    ->ocdAction($request, $record);

            case 'work_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\WorkRequestController::class)
                        ->approveInApp($request, $record);
                }
                // GSU Approved → FAD action
                $request->merge(['action' => 'approve']);
                return app(\App\Http\Controllers\WorkRequestController::class)
                    ->fadAction($request, $record);

            case 'service_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\ServiceRequestController::class)
                        ->approveInApp($request, $record);
                }
                // Approved (DC done) → FAD action
                $request->merge(['action' => 'approve']);
                return app(\App\Http\Controllers\ServiceRequestController::class)
                    ->fadAction($request, $record);

            case 'messengerial_requests':
                $request->merge(['action' => 'approve']);
                if ($status === 'Approved') {
                    return app(\App\Http\Controllers\MessengerialController::class)
                        ->ocdAction($request, $record);
                }
                return app(\App\Http\Controllers\MessengerialController::class)
                    ->divisionChiefAction($request, $record);

            case 'gate_passes':
                if ($status === 'Division Approved') {
                    $request->merge(['action' => 'approve']);
                    return app(\App\Http\Controllers\HumanResource\GatePassController::class)
                        ->approveByOCDInApp($request, $record->id);
                }
                $request->merge(['status' => 'Division Approved']);
                return app(\App\Http\Controllers\HumanResource\GatePassController::class)
                    ->update($request, $record->id);

            case 'leave_applications':
                $stage = $this->resolveLeaveStage($user, $record);
                $request->merge(['stage' => $stage, 'action' => 'approve']);
                return app(\App\Http\Controllers\HR\LeaveApplicationController::class)
                    ->approve($request, $record);
        }

        abort(404);
    }

    /**
     * Delegate the decline action to the existing per-module controller method.
     * Uses the record's current status to determine which approval stage is being acted on.
     */
    private function delegateDecline(Request $request, string $type, $record)
    {
        $user   = $request->user();
        $status = $record->status ?? '';

        switch ($type) {
            case 'it_job_requests':
                if ($status === 'Pending Division Chief Approval') {
                    $request->merge(['action' => 'reject']);
                    return app(\App\Http\Controllers\ITJobRequestController::class)
                        ->approveByDivisionChief($request, $record);
                }
                $request->merge(['action' => 'reject']);
                return app(\App\Http\Controllers\ITJobRequestController::class)
                    ->approveByOCD($request, $record);

            case 'vehicle_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\VehicleRequestController::class)
                        ->declineInApp($request, $record);
                }
                $request->merge(['action' => 'reject']);
                return app(\App\Http\Controllers\VehicleRequestController::class)
                    ->approveByOCDInApp($request, $record);

            case 'facility_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\FacilityRequestController::class)
                        ->declineInApp($request, $record);
                }
                $request->merge(['action' => 'reject']);
                return app(\App\Http\Controllers\FacilityRequestController::class)
                    ->ocdAction($request, $record);

            case 'work_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\WorkRequestController::class)
                        ->declineInApp($request, $record);
                }
                $request->merge(['action' => 'reject']);
                return app(\App\Http\Controllers\WorkRequestController::class)
                    ->fadAction($request, $record);

            case 'service_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\ServiceRequestController::class)
                        ->declineInApp($request, $record);
                }
                $request->merge(['action' => 'reject']);
                return app(\App\Http\Controllers\ServiceRequestController::class)
                    ->fadAction($request, $record);

            case 'messengerial_requests':
                $request->merge(['action' => 'reject']);
                if ($status === 'Approved') {
                    return app(\App\Http\Controllers\MessengerialController::class)
                        ->ocdAction($request, $record);
                }
                return app(\App\Http\Controllers\MessengerialController::class)
                    ->divisionChiefAction($request, $record);

            case 'gate_passes':
                if ($status === 'Division Approved') {
                    $request->merge(['action' => 'reject']);
                    return app(\App\Http\Controllers\HumanResource\GatePassController::class)
                        ->approveByOCDInApp($request, $record->id);
                }
                $request->merge([
                    'status'         => 'Division Declined',
                    'decline_reason' => $request->input('reason'),
                ]);
                return app(\App\Http\Controllers\HumanResource\GatePassController::class)
                    ->update($request, $record->id);

            case 'leave_applications':
                $stage = $this->resolveLeaveStage($user, $record);
                $request->merge(['stage' => $stage, 'action' => 'reject']);
                return app(\App\Http\Controllers\HR\LeaveApplicationController::class)
                    ->approve($request, $record);
        }

        abort(404);
    }

    /**
     * Determine the leave approval stage based on the user's role and the application's current status.
     */
    private function resolveLeaveStage($user, LeaveApplication $record): string
    {
        if ($user->hasRole('OCD')) {
            return 'campus_director';
        }
        if ($user->hasRole('DivisionChief')) {
            return 'division_chief';
        }
        // HR Officer
        return 'hr_officer';
    }
}
