<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2CoreItem;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use App\Services\DigitalSignatureService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\IPCRV2\StrategicFunctionService;
use App\Services\PersonNameFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DivisionChiefIpcrV2Controller extends Controller
{
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private StrategicFunctionService $strategic,
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService(),
        private DigitalSignatureService $sigService = new DigitalSignatureService(),
        private PersonNameFormatter $nameFormatter = new PersonNameFormatter(),
        private \App\Services\PerformanceManagement\CommitteeIpcrRatingService $committeeRating = new \App\Services\PerformanceManagement\CommitteeIpcrRatingService(),
    ) {}

    public function index(Request $request)
    {
        $records = IpcrV2Record::with('user.pds.personalInfo', 'period')
            ->whereHas('user', function ($q) use ($request) {
                $q->where('division_id', $request->user()->division_id);
            })
            ->latest('id')
            ->get();
        $records->each(fn ($record) => $record->user->setAttribute('formatted_name', $this->nameFormatter->formal($record->user)));

        return Inertia::render('IPCRV2/DivisionChiefIpcrV2Index', ['records' => $records]);
    }

    public function show(Request $request, int $id)
    {
        $record = IpcrV2Record::with(['user.pds.personalInfo', 'coreItems', 'supportItems', 'period', 'coachingSessions', 'statusLogs.actor'])->findOrFail($id);

        abort_unless(
            $request->user()->hasRole('OCD') || $record->user?->division_id === $request->user()->division_id,
            403,
            'This employee is not in your division.'
        );

        $record->user->setAttribute('formatted_name', $this->nameFormatter->formal($record->user));
        $ocdUser = \App\Models\User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/DivisionChiefIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'ocdUser' => $ocdUser ? [...$ocdUser->only('name', 'position'), 'formatted_name' => $this->nameFormatter->formal($ocdUser)] : null,
            'summary' => $this->summaryService->buildRows($record),
            'isMutable' => $record->isMutable(),
            'hasPin' => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
        ]);
    }

    public function approveTargets(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);

        $data = $request->validate(['pin' => 'nullable|string']);
        $this->sigService->assertSigningPin($request->user(), $data['pin'] ?? null);

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
            extra: ['target_approved_at' => now()],
            actor: $request->user(),
            actionType: 'approved',
            signedViaPin: ! empty($request->user()->signature_pin),
        );

        return back()->with('success', 'Targets approved.');
    }

    public function disapproveTargets(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);

        $data = $request->validate(['remarks' => 'required|string|max:1000']);

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_RETURNED,
            actor: $request->user(),
            remarks: $data['remarks'],
            actionType: 'returned',
        );

        return back()->with('success', 'Returned for revision.');
    }

    public function rateCoreItem(Request $request, int $id, IpcrV2CoreItem $coreItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        abort_if($coreItem->ipcr_v2_id !== $record->id, 404);

        // A WDP-tagged materialized row (success_indicator set) is rated
        // Quality/Efficiency/Timeliness like a Support item; an untagged
        // (teaching-load) row keeps the fixed CSC 4-criteria rubric.
        if ($coreItem->success_indicator !== null) {
            $data = $request->validate([
                'quality_rating' => 'required|integer|min:1|max:5',
                'efficiency_rating' => 'required|integer|min:1|max:5',
                'timeliness_rating' => 'required|integer|min:1|max:5',
                'remarks' => 'nullable|string|max:1000',
            ]);

            $data['row_average'] = round(($data['quality_rating'] + $data['efficiency_rating'] + $data['timeliness_rating']) / 3, 2);

            $coreItem->update($data);

            return back()->with('success', 'Rated.');
        }

        $data = $request->validate([
            'student_feedback_rating' => 'required|integer|min:1|max:5',
            'supervisor_feedback_rating' => 'required|integer|min:1|max:5',
            'im_development_rating' => 'required|integer|min:1|max:5',
            'timeliness_rating' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $data['row_average'] = round(
            $data['student_feedback_rating'] * 0.30
            + $data['supervisor_feedback_rating'] * 0.20
            + $data['im_development_rating'] * 0.20
            + $data['timeliness_rating'] * 0.30,
            2
        );

        $coreItem->update($data);

        return back()->with('success', 'Rated.');
    }

    public function rateSupportItem(Request $request, int $id, IpcrV2SupportItem $supportItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        abort_if($supportItem->ipcr_v2_id !== $record->id, 404);
        abort_if(
            $this->committeeRating->isCommitteeSourced($supportItem),
            403,
            'This item is rated via its Committee Assignment, not here.'
        );

        $data = $request->validate([
            'quality_rating' => 'required|integer|min:1|max:5',
            'efficiency_rating' => 'required|integer|min:1|max:5',
            'timeliness_rating' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $data['row_average'] = round(($data['quality_rating'] + $data['efficiency_rating'] + $data['timeliness_rating']) / 3, 2);

        $supportItem->update($data);

        return back()->with('success', 'Rated.');
    }

    /**
     * Moves the record to PMT. A record still sitting in "Submitted for
     * Rating" has no explicit "mark as rated" action in the UI — this
     * bridges STATUS_FOR_RATING -> STATUS_RATED -> STATUS_SUBMITTED_PMT as
     * two audited transitions in one request, rather than adding a new
     * button/route/permission for a milestone with no independent meaning
     * to the Division Chief.
     */
    public function submitToPMT(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanEndorse($request->user(), $record);

        if ($record->status === IpcrV2WorkflowService::STATUS_FOR_RATING) {
            $this->workflow->transition(
                $record,
                IpcrV2WorkflowService::STATUS_RATED,
                actor: $request->user(),
                actionType: 'rated',
            );
            $record = $record->fresh();
        }

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
            extra: ['submitted_for_pmtreview_at' => now()],
            actor: $request->user(),
            actionType: 'submitted',
        );

        return back()->with('success', 'Submitted to PMT.');
    }

    public function updateComments(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        $this->workflow->assertMutable($record);

        $data = $request->validate(['comments_recommendations' => 'nullable|string|max:2000']);
        $record->update($data);

        return back()->with('success', 'Comments saved.');
    }
}
