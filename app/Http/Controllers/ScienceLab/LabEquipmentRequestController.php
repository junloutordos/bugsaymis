<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\ScienceLab\LabEquipment;
use App\Models\ScienceLab\LabEquipmentRequest;
use App\Models\ScienceLab\LabRepairTicket;
use App\Services\ScienceLab\LabControlNumberService;
use App\Services\ScienceLab\LabPdfService;
use App\Traits\SignsLabDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LabEquipmentRequestController extends Controller
{
    use SignsLabDocuments;

    public function __construct(private LabControlNumberService $controlNumbers) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $canManage = $user->hasPermission('lab.manage');

        $requests = LabEquipmentRequest::with(['items.equipment:id,description,property_no', 'requester:id,name', 'approver:id,name'])
            ->when(! $canManage, fn ($q) => $q->where('requested_by_id', $user->id))
            ->when($request->string('status')->value(), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')->get();

        return Inertia::render('ScienceLab/EquipmentRequests', [
            'requests'   => $requests,
            'equipment'  => LabEquipment::orderBy('description')->get(['id', 'description', 'property_no', 'status']),
            'schoolYear' => SchoolYear::where('is_current', true)->first(['id', 'name']),
            'canManage'  => $canManage,
            'hasPin'     => ! empty($user->signature_pin),
            'filters'    => $request->only('status'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateHeader($request);
        $items = $request->validate([
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.lab_equipment_id'  => ['nullable', 'integer', 'exists:lab_equipment,id'],
            'items.*.quantity'          => ['required', 'numeric', 'gt:0'],
            'items.*.item'              => ['required', 'string', 'max:255'],
            'items.*.description'       => ['nullable', 'string', 'max:255'],
        ])['items'];

        $req = DB::transaction(function () use ($data, $items) {
            $data['control_no']      = $this->controlNumbers->next(LabControlNumberService::EQUIPMENT, 'lab_equipment_requests');
            $data['school_year_id']  = SchoolYear::where('is_current', true)->value('id');
            $data['requested_by_id'] = Auth::id();
            $data['requester_name']  = Auth::user()->name;
            $data['status']          = 'pending';
            $req = LabEquipmentRequest::create($data);
            foreach ($items as $it) {
                $req->items()->create($it);
            }

            return $req;
        });

        return back()->with('success', "Equipment request {$req->control_no} filed.");
    }

    public function endorse(Request $request, LabEquipmentRequest $equipmentRequest)
    {
        return $this->stampSignature($request, $equipmentRequest, 'endorsed', 'endorsed_by_id', 'endorsed_at', ['pending'], 'endorsement');
    }

    public function approve(Request $request, LabEquipmentRequest $equipmentRequest)
    {
        abort_unless(Auth::user()->hasPermission('lab.manage'), 403);

        return $this->stampSignature($request, $equipmentRequest, 'approved', 'approved_by_id', 'approved_at', ['pending', 'endorsed'], 'approval');
    }

    public function issue(Request $request, LabEquipmentRequest $equipmentRequest)
    {
        abort_unless(Auth::user()->hasPermission('lab.manage'), 403);
        abort_unless($equipmentRequest->status === 'approved', 422, 'Only approved requests can be issued.');

        $data = $request->validate([
            'items'                    => ['required', 'array'],
            'items.*.id'               => ['required', 'integer'],
            'items.*.issued_condition' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($equipmentRequest, $data) {
            foreach ($data['items'] as $row) {
                $equipmentRequest->items()->whereKey($row['id'])
                    ->update(['issued_condition' => $row['issued_condition'] ?? null]);
            }
            $equipmentRequest->update([
                'issued_by_id' => Auth::id(),
                'issued_at'    => now(),
                'status'       => 'issued',
            ]);
        });

        return back()->with('success', 'Equipment issued to borrower.');
    }

    public function returnItems(Request $request, LabEquipmentRequest $equipmentRequest)
    {
        abort_unless(Auth::user()->hasPermission('lab.manage'), 403);
        abort_unless($equipmentRequest->status === 'issued', 422, 'Only issued requests can be returned.');

        $data = $request->validate([
            'items'                      => ['required', 'array'],
            'items.*.id'                 => ['required', 'integer'],
            'items.*.returned_condition' => ['nullable', 'string', 'max:255'],
            'items.*.needs_repair'       => ['boolean'],
            'items.*.needs_replacement'  => ['boolean'],
        ]);

        DB::transaction(function () use ($equipmentRequest, $data) {
            foreach ($data['items'] as $row) {
                $item = $equipmentRequest->items()->whereKey($row['id'])->first();
                if (! $item) {
                    continue;
                }
                $item->update([
                    'returned_condition' => $row['returned_condition'] ?? null,
                    'needs_repair'       => $row['needs_repair'] ?? false,
                    'needs_replacement'  => $row['needs_replacement'] ?? false,
                ]);

                // Flag damaged equipment + open a repair ticket (CIM 4.4 §3.1.5–3.1.6).
                if (($row['needs_repair'] ?? false) && $item->lab_equipment_id) {
                    LabEquipment::whereKey($item->lab_equipment_id)->update(['status' => 'under_repair']);
                    LabRepairTicket::create([
                        'control_no'     => $this->controlNumbers->next(LabControlNumberService::REPAIR, 'lab_repair_tickets'),
                        'lab_equipment_id' => $item->lab_equipment_id,
                        'reported_by_id' => Auth::id(),
                        'problem'        => 'Damage on return — ' . ($row['returned_condition'] ?? 'see accountability form ' . $equipmentRequest->control_no),
                        'date_reported'  => now()->toDateString(),
                        'status'         => 'open',
                    ]);
                }
            }
            $equipmentRequest->update([
                'returned_received_by_id' => Auth::id(),
                'returned_at'             => now(),
                'status'                  => 'returned',
            ]);
        });

        return back()->with('success', 'Equipment checked in. Damaged items flagged for repair.');
    }

    public function decline(Request $request, LabEquipmentRequest $equipmentRequest)
    {
        abort_unless(Auth::user()->hasPermission('lab.manage'), 403);
        $data = $request->validate(['decline_reason' => ['required', 'string', 'max:500']]);
        $equipmentRequest->update(['status' => 'declined', 'decline_reason' => $data['decline_reason']]);

        return back()->with('success', 'Request declined.');
    }

    public function pdf(LabEquipmentRequest $equipmentRequest, LabPdfService $pdf)
    {
        $equipmentRequest->load(['items.equipment', 'requester:id,name', 'endorser:id,name', 'approver:id,name', 'schoolYear:id,name']);

        return $pdf->equipmentRequest($equipmentRequest);
    }

    private function stampSignature(Request $request, LabEquipmentRequest $model, string $status, string $byCol, string $atCol, array $allowed, string $stage)
    {
        $request->validate(['pin' => ['required', 'string']]);
        abort_unless(in_array($model->status, $allowed), 422, 'Request cannot transition from its current state.');
        $this->verifyLabPin(Auth::user(), $request->input('pin'));

        $model->update([$byCol => Auth::id(), $atCol => now(), 'status' => $status]);
        $this->signLabDocument(Auth::user(), $model, $stage, 'Laboratory Request and Equipment Accountability Form');

        return back()->with('success', ucfirst($stage) . ' recorded.');
    }

    private function validateHeader(Request $request): array
    {
        return $request->validate([
            'grade_level_section' => ['nullable', 'string', 'max:100'],
            'number_of_students'  => ['nullable', 'integer', 'min:0'],
            'subject'             => ['nullable', 'string', 'max:150'],
            'concurrent_topic'    => ['nullable', 'string', 'max:200'],
            'unit'                => ['nullable', 'string', 'max:100'],
            'teacher_in_charge'   => ['nullable', 'string', 'max:150'],
            'venue'               => ['nullable', 'string', 'max:150'],
            'date_start'          => ['required', 'date'],
            'date_end'            => ['nullable', 'date', 'after_or_equal:date_start'],
            'time_start'          => ['nullable', 'date_format:H:i'],
            'time_end'            => ['nullable', 'date_format:H:i'],
            'requester_type'      => ['required', Rule::in(['teacher', 'student'])],
            'student_group'       => ['nullable', 'array'],
            'student_group.*'     => ['nullable', 'string', 'max:150'],
            'remarks'             => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
