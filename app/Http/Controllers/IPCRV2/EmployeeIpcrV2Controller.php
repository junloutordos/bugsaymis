<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2CoreItem;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use App\Services\DigitalSignatureService;
use App\Services\IPCRV2\IpcrV2GenerationService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\IPCRV2\StrategicFunctionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeIpcrV2Controller extends Controller
{
    private const EDITABLE_STATUSES = [
        IpcrV2WorkflowService::STATUS_NEW_TARGET,
        IpcrV2WorkflowService::STATUS_RETURNED,
    ];

    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private IpcrV2GenerationService $generation,
        private StrategicFunctionService $strategic,
        private \App\Services\PerformanceManagement\IPCRWorkflowService $v1Chain,
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService,
        private DigitalSignatureService $sigService = new DigitalSignatureService()
    ) {}

    public function index(Request $request)
    {
        $records = IpcrV2Record::where('user_id', $request->user()->id)
            ->with('period')
            ->latest('id')
            ->get();

        return Inertia::render('IPCRV2/EmployeeIpcrV2Index', [
            'records' => $records,
            'openPeriods' => IPCRRatingPeriod::open()->get(['id', 'label', 'year', 'semester']),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'statusLogs.actor'])->findOrFail($id);

        $isOwner = $record->user_id === $request->user()->id;
        abort_unless(
            $isOwner || $this->workflow->canManage($request->user(), $record),
            403,
            "You are not this employee's immediate supervisor and cannot view this IPCR V2."
        );

        $supervisor = $this->v1Chain->immediateSupervisorFor($record->user)
            ?? ($record->user->hasRole('DivisionChief') ? \App\Models\User::havingRole('OCD')->first() : null);
        $ocdUser = \App\Models\User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/EmployeeIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'supervisor' => $supervisor?->only('name', 'position'),
            'ocdUser' => $ocdUser?->only('name', 'position'),
            'summary' => $this->summaryService->buildRows($record),
            'isOwner' => $isOwner,
            'isMutable' => $record->isMutable(),
            'hasPin' => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
        ]);
    }

    public function generateTargets(Request $request)
    {
        $data = $request->validate(['rating_period_id' => 'required|exists:ipcr_rating_periods,id']);
        $period = IPCRRatingPeriod::findOrFail($data['rating_period_id']);

        $record = $this->generation->generateTargets($request->user(), $period);

        return redirect()->route('employee-ipcr-v2.show', $record->id)->with('success', 'IPCR V2 targets generated.');
    }

    public function syncFunctions(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->assertMutable($record);

        $added = $this->generation->syncNewFunctions($record);

        return back()->with('success', $added > 0
            ? "Synced {$added} new function(s) from Employee Functions."
            : 'No new functions to sync.');
    }

    public function submitForReview(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);

        $data = $request->validate(['pin' => 'nullable|string']);
        $this->sigService->assertSigningPin($request->user(), $data['pin'] ?? null);

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_FOR_REVIEW,
            actor: $request->user(),
            actionType: 'submitted',
            signedViaPin: ! empty($request->user()->signature_pin),
        );

        return back()->with('success', 'Submitted for review.');
    }

    public function submitForRating(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);

        $data = $request->validate(['pin' => 'nullable|string']);
        $this->sigService->assertSigningPin($request->user(), $data['pin'] ?? null);

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_FOR_RATING,
            extra: ['submitted_for_rating_at' => now()],
            actor: $request->user(),
            actionType: 'submitted',
            signedViaPin: ! empty($request->user()->signature_pin),
        );

        return back()->with('success', 'Submitted for rating.');
    }

    public function updateCoreItem(Request $request, int $id, IpcrV2CoreItem $coreItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->assertMutable($record);
        abort_if($coreItem->ipcr_v2_id !== $record->id, 404);

        if ($coreItem->success_indicator !== null) {
            $data = $request->validate([
                'target' => 'nullable|string|max:1000',
                'actual_accomplishment' => 'nullable|string|max:1000',
                'mov_link' => 'nullable|string|max:500',
                'self_quality_rating' => 'nullable|integer|min:1|max:5',
                'self_efficiency_rating' => 'nullable|integer|min:1|max:5',
                'self_timeliness_rating' => 'nullable|integer|min:1|max:5',
            ]);
        } else {
            $data = $request->validate([
                'target' => 'nullable|string|max:1000',
                'actual_accomplishment' => 'nullable|string|max:1000',
                'mov_link' => 'nullable|string|max:500',
                'self_student_feedback_rating' => 'nullable|integer|min:1|max:5',
                'self_supervisor_feedback_rating' => 'nullable|integer|min:1|max:5',
                'self_im_development_rating' => 'nullable|integer|min:1|max:5',
                'self_timeliness_rating' => 'nullable|integer|min:1|max:5',
            ]);
        }

        $coreItem->fill($data);

        if ($coreItem->success_indicator !== null) {
            $ratings = [$coreItem->self_quality_rating, $coreItem->self_efficiency_rating, $coreItem->self_timeliness_rating];
            $coreItem->self_row_average = in_array(null, $ratings, true) ? null : round(array_sum($ratings) / 3, 2);
        } else {
            $ratings = [
                $coreItem->self_student_feedback_rating, $coreItem->self_supervisor_feedback_rating,
                $coreItem->self_im_development_rating, $coreItem->self_timeliness_rating,
            ];
            $coreItem->self_row_average = in_array(null, $ratings, true)
                ? null
                : round($ratings[0] * 0.30 + $ratings[1] * 0.20 + $ratings[2] * 0.20 + $ratings[3] * 0.30, 2);
        }

        $coreItem->save();

        return back()->with('success', 'Updated.');
    }

    public function updateSupportItem(Request $request, int $id, IpcrV2SupportItem $supportItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->assertMutable($record);
        abort_if($supportItem->ipcr_v2_id !== $record->id, 404);

        $data = $request->validate([
            'target' => 'nullable|string|max:1000',
            'actual_accomplishment' => 'nullable|string|max:1000',
            'mov_link' => 'nullable|string|max:500',
            'self_quality_rating' => 'nullable|integer|min:1|max:5',
            'self_efficiency_rating' => 'nullable|integer|min:1|max:5',
            'self_timeliness_rating' => 'nullable|integer|min:1|max:5',
        ]);

        $supportItem->fill($data);

        $ratings = [$supportItem->self_quality_rating, $supportItem->self_efficiency_rating, $supportItem->self_timeliness_rating];
        $supportItem->self_row_average = in_array(null, $ratings, true) ? null : round(array_sum($ratings) / 3, 2);

        $supportItem->save();

        return back()->with('success', 'Updated.');
    }

    public function destroy(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->assertMutable($record);
        abort_unless(
            in_array($record->status, self::EDITABLE_STATUSES, true),
            403,
            'Only IPCR V2 records that are new or returned for revision can be deleted.'
        );

        $record->delete();

        return back()->with('success', 'IPCR V2 record deleted.');
    }
}
