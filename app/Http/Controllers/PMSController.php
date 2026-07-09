<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStep;
use App\Http\Traits\SignsDocuments;
use App\Models\PMS;
use App\Models\User;
use App\Models\ICTEquipment;
use App\Services\DigitalSignatureService;
use App\Services\SnapshotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PMSController extends Controller
{
    use SignsDocuments;

    public function __construct(
        private SnapshotService         $snapshots,
        private DigitalSignatureService $sigService,
    ) {}

    public function index(Request $request)
    {
        $search    = $request->input('search');
        $status    = $request->input('status');
        $frequency = $request->input('frequency');

        $query = PMS::with([
            'performedBy:id,name',
            'equipments' => function ($q) {
                $q->select(
                    'ict_equipments.id',
                    'description',
                    'room_id',
                    'owner_id',
                    'serial_no',
                    'category'
                )
                ->with([
                    'room:id,name,code',
                    'owner:id,name',
                ]);
            },
            'dates',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('office_area', 'like', "%{$search}%")
                  ->orWhere('school_year', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($frequency) {
            $query->where('frequency', $frequency);
        }

        $pmsSchedules = $query->paginate(15)->withQueryString();

        $users = User::select('id','name')->orderBy('name')->get();

        $equipments = ICTEquipment::with([
            'room:id,name,code',
            'owner:id,name',
        ])->orderBy('description')->get();

        $user = $request->user();

        return Inertia::render('ITJobRequests/PMS', [
            'pmsSchedules' => $pmsSchedules,
            'users'        => $users,
            'equipments'   => $equipments,
            'filters'      => $request->only('search', 'status', 'frequency'),
            'hasPin'       => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
        ]);
    }



    public function store(Request $request)
    {
        $data = $request->validate([
            'title'                  => 'required|string|max:255',
            'frequency'              => 'required|string|max:255',
            'school_year'            => 'required|string|max:20',   // ✅ new
            'office_area'            => 'required|string|max:255',  // ✅ new
            'status'                 => 'required|string|max:255',
            'remarks'                => 'nullable|string',
            'schedule_dates'         => 'required|array',
            'schedule_dates.*.date'  => 'required|date',
            'pin'                    => 'nullable|string',
        ]);

        // Create PMS
        $pms = PMS::create([
            'title'           => $data['title'],
            'frequency'       => $data['frequency'],
            'school_year'     => $data['school_year'], // ✅
            'office_area'     => $data['office_area'],      // ✅
            'status'          => $data['status'],
            'remarks'         => $data['remarks'] ?? null,
            'performed_by'    => Auth::id(),
            'created_by'      => Auth::id(),
            'approval_status' => 'pending',
        ]);

        // Store multiple schedule dates
        foreach ($data['schedule_dates'] as $date) {
            $pms->dates()->create([
                'schedule_date' => $date['date'],
            ]);
        }

        // Prepared By — creator's digital signature (best-effort, matches
        // performSign() convention used across the app: doesn't block creation
        // if the PIN is missing/wrong, just skips recording the signature).
        $this->performSign(
            $request,
            PMS::class,
            $pms->id,
            'prepared_by',
            "PMS Schedule: {$pms->title}",
            PMS::class . $pms->id . 'prepared_by',
        );

        $this->snapshots->recordApproval(
            approvable: $pms,
            step:       ApprovalStep::PMS_PREPARED,
            sequence:   1,
            action:     'prepared',
            approver:   $request->user(),
        );

        return redirect()->back()->with('success', 'PMS Schedule added successfully.');
    }

    public function update(Request $request, PMS $pms)
    {
        $data = $request->validate([
            'title'                  => 'required|string|max:255',
            'frequency'              => 'required|string|max:255',
            'school_year'            => 'required|string|max:20',   // ✅ new
            'office_area'            => 'required|string|max:255',  // ✅ new
            'status'                 => 'required|string|max:255',
            'remarks'                => 'nullable|string',
            'schedule_dates'         => 'required|array',
            'schedule_dates.*.date'  => 'required|date',
        ]);

        // Update PMS
        $pms->update([
            'title'        => $data['title'],
            'frequency'    => $data['frequency'],
            'school_year'  => $data['school_year'], // ✅
            'office_area'  => $data['office_area'],      // ✅
            'status'       => $data['status'],
            'remarks'      => $data['remarks'] ?? null,
            'performed_by' => Auth::id(),
        ]);

        // Replace old dates with new ones
        $pms->dates()->delete();
        foreach ($data['schedule_dates'] as $date) {
            $pms->dates()->create([
                'schedule_date' => $date['date'],
            ]);
        }

        return redirect()->back()->with('success', 'PMS Schedule updated successfully.');
    }

    public function destroy(PMS $pms)
    {
        $pms->delete();
        return redirect()->back()->with('success', 'PMS Schedule deleted successfully.');
    }

    /**
     * OCD approves a PMS schedule (Noted By) — PIN-gated digital signature +
     * immutable approval snapshot. Approval runs in parallel with the
     * schedule's normal Pending/Ongoing/Completed operational status; it does
     * not block PMS activity logging.
     */
    public function approveByOCD(Request $request, PMS $pms)
    {
        if ($pms->approval_status !== 'pending') {
            return back()->with('error', 'This schedule has already been acted upon.');
        }

        $pms->update([
            'approval_status' => 'approved',
            'approved_by'     => $request->user()->id,
            'approved_at'     => now(),
        ]);

        $this->snapshots->recordApproval(
            approvable: $pms,
            step:       ApprovalStep::PMS_OCD,
            sequence:   2,
            action:     'approved',
            approver:   $request->user(),
        );

        $this->performSign(
            $request,
            PMS::class,
            $pms->id,
            'noted_by',
            "PMS Schedule: {$pms->title}",
            PMS::class . $pms->id . 'noted_by',
        );

        return back()->with('success', 'PMS Schedule approved.');
    }

    /**
     * OCD declines a PMS schedule. No digital signature is recorded on
     * decline (matches the convention used elsewhere — rejections aren't
     * "signed"), but the decision is still captured immutably.
     */
    public function declineByOCD(Request $request, PMS $pms)
    {
        if ($pms->approval_status !== 'pending') {
            return back()->with('error', 'This schedule has already been acted upon.');
        }

        $data = $request->validate(['reason' => 'required|string|min:1|max:1000']);

        $pms->update([
            'approval_status' => 'declined',
            'declined_reason' => $data['reason'],
        ]);

        $this->snapshots->recordApproval(
            approvable: $pms,
            step:       ApprovalStep::PMS_OCD,
            sequence:   2,
            action:     'declined',
            approver:   $request->user(),
            remarks:    $data['reason'],
        );

        return back()->with('success', 'PMS Schedule declined.');
    }

    public function assignEquipments(Request $request, $pmsId)
    {
        $data = $request->validate([
            'equipment_ids'   => 'required|array',
            'equipment_ids.*' => 'exists:ict_equipments,id',
        ]);

        $pms = PMS::findOrFail($pmsId);
        $pms->equipments()->syncWithoutDetaching($data['equipment_ids']);

        return redirect()->back()->with('success', 'Equipments assigned to PMS successfully.');
    }

    public function showEquipments(PMS $pms)
    {
        // Load equipments with histories and related room + owner, also PMS dates
        $pms->load([
            'equipments.histories',
            'equipments.room:id,name,code',   // room for location
            'equipments.owner:id,name',       // optional, if needed
            'dates',
            'createdBy:id,name,position',
            'approvedBy:id,name,position',
        ]);

        $sigs = $this->loadSigsForPrint(PMS::class, $pms->id);

        // Map schedule dates
        $pms->schedule_dates = $pms->dates->map(fn($d) => [
            'schedule_date' => $d->schedule_date,
            'status'        => $d->status,
        ]);

        // Map equipments with histories and room location
        $equipments = $pms->equipments->map(function ($eq) {
            return [
                'id'            => $eq->id,
                'description'   => $eq->description,
                'serial_no'     => $eq->serial_no,
                'category'      => $eq->category,
                'room'          => $eq->room ? $eq->room->code : 'No Location',
                'owner'         => $eq->owner ? $eq->owner->name : 'No Owner',
                'history_dates' => $eq->histories->pluck('pms_date'),
            ];
        });

        return Inertia::render('ITJobRequests/PMSEquipments', [
            'pms'        => $pms,
            'equipments' => $equipments,
            'prepared_by' => [
                'name'      => $pms->createdBy?->name,
                'position'  => $pms->createdBy?->position,
                'signed_at' => $sigs['prepared_by']['signed_at'] ?? null,
            ],
            'noted_by' => $pms->approval_status === 'approved' ? [
                'name'      => $sigs['noted_by']['name'] ?? $pms->approvedBy?->name,
                'position'  => $pms->approvedBy?->position,
                'signed_at' => $sigs['noted_by']['signed_at'] ?? optional($pms->approved_at)->toIso8601String(),
            ] : null,
            'approval_status' => $pms->approval_status,
            'declined_reason' => $pms->declined_reason,
        ]);
    }




}
