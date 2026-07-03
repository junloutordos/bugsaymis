<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\ScienceLab\LabConsumable;
use App\Models\ScienceLab\LabReagentRequest;
use App\Services\ScienceLab\LabControlNumberService;
use App\Services\ScienceLab\LabInventoryService;
use App\Services\ScienceLab\LabPdfService;
use App\Traits\SignsLabDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LabReagentRequestController extends Controller
{
    use SignsLabDocuments;

    public function __construct(
        private LabControlNumberService $controlNumbers,
        private LabInventoryService $inventory,
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $canManage = $user->hasPermission('lab.manage');

        $requests = LabReagentRequest::with(['items.consumable:id,name,unit_of_measure', 'requester:id,name', 'approver:id,name'])
            ->when(! $canManage, fn ($q) => $q->where('requested_by_id', $user->id))
            ->when($request->string('status')->value(), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')->get();

        return Inertia::render('ScienceLab/ReagentRequests', [
            'requests'   => $requests,
            'reagents'   => LabConsumable::whereIn('type', ['reagent', 'chemical'])->where('status', 'active')
                                ->orderBy('name')->get(['id', 'name', 'unit_of_measure', 'is_chemical', 'sds_path'])
                                ->map(fn ($c) => [
                                    'id' => $c->id, 'name' => $c->name, 'unit' => $c->unit_of_measure,
                                    'is_chemical' => $c->is_chemical, 'has_sds' => (bool) $c->sds_path,
                                    'balance' => $this->inventory->balance($c->id),
                                ]),
            'schoolYear' => SchoolYear::where('is_current', true)->first(['id', 'name']),
            'canManage'  => $canManage,
            'hasPin'     => ! empty($user->signature_pin),
            'filters'    => $request->only('status'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
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
            'sds_acknowledged'    => ['accepted'], // §3.1.11 — must certify SDS read
            'remarks'             => ['nullable', 'string', 'max:1000'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.lab_consumable_id' => ['nullable', 'integer', 'exists:lab_consumables,id'],
            'items.*.quantity'    => ['required', 'numeric', 'gt:0'],
            'items.*.reagent'     => ['required', 'string', 'max:255'],
            'items.*.sds_read'    => ['boolean'],
        ]);

        $items = $data['items'];
        unset($data['items']);

        $req = DB::transaction(function () use ($data, $items) {
            $data['control_no']      = $this->controlNumbers->next(LabControlNumberService::REAGENT, 'lab_reagent_requests');
            $data['school_year_id']  = SchoolYear::where('is_current', true)->value('id');
            $data['requested_by_id'] = Auth::id();
            $data['requester_name']  = Auth::user()->name;
            $data['sds_acknowledged']= true;
            $data['status']          = 'pending';
            $req = LabReagentRequest::create($data);
            foreach ($items as $it) {
                $req->items()->create($it);
            }

            return $req;
        });

        return back()->with('success', "Reagent request {$req->control_no} filed.");
    }

    public function endorse(Request $request, LabReagentRequest $reagentRequest)
    {
        return $this->stamp($request, $reagentRequest, 'endorsed', 'endorsed_by_id', 'endorsed_at', ['pending'], 'endorsement');
    }

    public function approve(Request $request, LabReagentRequest $reagentRequest)
    {
        abort_unless(Auth::user()->hasPermission('lab.manage'), 403);

        return $this->stamp($request, $reagentRequest, 'approved', 'approved_by_id', 'approved_at', ['pending', 'endorsed'], 'approval');
    }

    /**
     * Release reagents to the requesting unit and deduct from inventory (FEFO).
     */
    public function release(Request $request, LabReagentRequest $reagentRequest)
    {
        abort_unless(Auth::user()->hasPermission('lab.manage'), 403);
        abort_unless($reagentRequest->status === 'approved', 422, 'Only approved requests can be released.');

        $data = $request->validate([
            'items'                 => ['required', 'array'],
            'items.*.id'            => ['required', 'integer'],
            'items.*.issued_amount' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            DB::transaction(function () use ($reagentRequest, $data) {
                $reagentRequest->load('items');
                foreach ($data['items'] as $row) {
                    $item = $reagentRequest->items->firstWhere('id', $row['id']);
                    if (! $item) {
                        continue;
                    }
                    $item->update(['issued_amount' => $row['issued_amount'] ?? null]);

                    // Deduct linked reagent stock (FEFO) when a catalogue item is set.
                    if ($item->lab_consumable_id) {
                        $consumable = LabConsumable::find($item->lab_consumable_id);
                        if ($consumable) {
                            $this->inventory->issue($consumable, (float) $item->quantity, [
                                'reference_id'     => $reagentRequest->id,
                                'reference_number' => $reagentRequest->control_no,
                                'transaction_date' => now()->toDateString(),
                                'remarks'          => 'Reagent request ' . $reagentRequest->control_no,
                            ], Auth::id());
                        }
                    }
                }
                $reagentRequest->update([
                    'released_by_id' => Auth::id(),
                    'released_at'    => now(),
                    'status'         => 'released',
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['release' => $e->getMessage()]);
        }

        return back()->with('success', 'Reagents released and inventory updated.');
    }

    public function decline(Request $request, LabReagentRequest $reagentRequest)
    {
        abort_unless(Auth::user()->hasPermission('lab.manage'), 403);
        $data = $request->validate(['decline_reason' => ['required', 'string', 'max:500']]);
        $reagentRequest->update(['status' => 'declined', 'decline_reason' => $data['decline_reason']]);

        return back()->with('success', 'Request declined.');
    }

    public function pdf(LabReagentRequest $reagentRequest, LabPdfService $pdf)
    {
        $reagentRequest->load(['items.consumable', 'requester:id,name', 'endorser:id,name', 'approver:id,name', 'releaser:id,name', 'schoolYear:id,name']);

        return $pdf->reagentRequest($reagentRequest);
    }

    private function stamp(Request $request, LabReagentRequest $model, string $status, string $byCol, string $atCol, array $allowed, string $stage)
    {
        $request->validate(['pin' => ['required', 'string']]);
        abort_unless(in_array($model->status, $allowed), 422, 'Request cannot transition from its current state.');
        $this->verifyLabPin(Auth::user(), $request->input('pin'));

        $model->update([$byCol => Auth::id(), $atCol => now(), 'status' => $status]);
        $this->signLabDocument(Auth::user(), $model, $stage, 'Reagent Request Form');

        return back()->with('success', ucfirst($stage) . ' recorded.');
    }
}
