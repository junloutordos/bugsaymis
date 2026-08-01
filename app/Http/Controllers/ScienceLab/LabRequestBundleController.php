<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Room;
use App\Models\ScienceLab\LabConsumable;
use App\Models\ScienceLab\LabEquipment;
use App\Models\ScienceLab\LabRequestBundle;
use App\Models\ScienceLab\ScienceLabProfile;
use App\Services\ScienceLab\LabInventoryService;
use App\Services\ScienceLab\LabRequestWorkflowService;
use App\Traits\SignsLabDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LabRequestBundleController extends Controller
{
    use SignsLabDocuments;

    public function __construct(
        private LabRequestWorkflowService $workflow,
        private LabInventoryService $inventory,
    ) {}

    public function index()
    {
        $user = Auth::user();
        $canManage = $user->hasPermission('lab.manage');
        $bundles = LabRequestBundle::with([
            'requester:id,name', 'endorser:id,name', 'approver:id,name',
            'reservation.room:id,name', 'equipmentRequest.items.equipment:id,description,property_no',
            'reagentRequest.items.consumable:id,name,unit_of_measure', 'csmResponse:id,respondable_type,respondable_id',
        ])->when(! $canManage, fn ($q) => $q->where(fn ($scope) => $scope
            ->where('requested_by_id', $user->id)
            ->orWhere('assigned_endorser_id', $user->id)
            ->orWhere('assigned_approver_id', $user->id)))
            ->latest()->get();

        $roomIds = ScienceLabProfile::where('status', 'active')->pluck('room_id');
        $reagents = LabConsumable::whereIn('type', ['reagent', 'chemical'])
            ->where('status', 'active')->orderBy('name')->get()
            ->map(fn ($item) => [
                'id' => $item->id, 'name' => $item->name, 'unit' => $item->unit_of_measure,
                'balance' => $this->inventory->balance($item->id),
            ]);

        return Inertia::render('ScienceLab/Requests', [
            'bundles' => $bundles,
            'labRooms' => Room::whereIn('id', $roomIds)->orderBy('name')->get(['id', 'name']),
            'equipment' => LabEquipment::where('status', 'active')->orderBy('description')->get(['id', 'description', 'property_no']),
            'reagents' => $reagents,
            'endorsers' => $this->workflow->endorserOptions($user),
            'requesterType' => $this->workflow->requesterType($user),
            'schoolYear' => SchoolYear::where('is_current', true)->first(['id', 'name']),
            'hasOutstandingCsm' => $this->workflow->hasOutstandingCsm($user),
            'hasPin' => ! empty($user->signature_pin),
            'currentUserId' => $user->id,
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request)
    {
        $bundle = $this->workflow->createForUser(Auth::user(), $this->validated($request));
        return back()->with('success', "Laboratory request {$bundle->reference_no} submitted.");
    }

    public function update(Request $request, LabRequestBundle $bundle)
    {
        $this->workflow->updateForUser($bundle, Auth::user(), $this->validated($request));
        return back()->with('success', 'Laboratory request updated.');
    }

    public function endorse(Request $request, LabRequestBundle $bundle)
    {
        $request->validate(['pin' => ['required', 'string']]);
        $this->verifyLabPin(Auth::user(), $request->input('pin'));
        $this->workflow->endorse($bundle, Auth::user());
        $bundle->load(['reservation', 'equipmentRequest', 'reagentRequest']);
        foreach (array_filter([$bundle->reservation, $bundle->equipmentRequest, $bundle->reagentRequest]) as $form) {
            $this->signLabDocument(Auth::user(), $form, 'endorsement', 'Science Laboratory Request');
        }
        return back()->with('success', 'Request package endorsed.');
    }

    public function approve(Request $request, LabRequestBundle $bundle)
    {
        $request->validate(['pin' => ['required', 'string']]);
        $this->verifyLabPin(Auth::user(), $request->input('pin'));
        $this->workflow->approve($bundle, Auth::user());
        $bundle->load(['reservation', 'equipmentRequest', 'reagentRequest']);
        foreach (array_filter([$bundle->reservation, $bundle->equipmentRequest, $bundle->reagentRequest]) as $form) {
            $this->signLabDocument(Auth::user(), $form, 'approval', 'Science Laboratory Request');
        }
        return back()->with('success', 'Request package approved.');
    }

    public function cancel(Request $request, LabRequestBundle $bundle)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->workflow->cancel($bundle, Auth::user(), $data['reason']);
        return back()->with('success', 'Request package cancelled.');
    }

    public function decline(Request $request, LabRequestBundle $bundle)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->workflow->decline($bundle, Auth::user(), $data['reason']);
        return back()->with('success', 'Request package declined.');
    }

    public function complete(LabRequestBundle $bundle)
    {
        $this->workflow->completeReservationOnly($bundle, Auth::user());
        return back()->with('success', 'Laboratory activity completed. The requester may now submit CSM feedback.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'assigned_endorser_id' => ['nullable', 'integer', 'exists:users,id'],
            'grade_level_section' => ['nullable', 'string', 'max:100'],
            'number_of_students' => ['nullable', 'integer', 'min:0'],
            'subject' => ['required', 'string', 'max:150'],
            'teacher_in_charge' => ['required', 'string', 'max:150'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'date_start' => ['required', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
            'student_group' => ['nullable', 'array'],
            'student_group.*' => ['nullable', 'string', 'max:150'],
            'concurrent_topic' => ['nullable', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:100'],
            'venue' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'needs_equipment' => ['required', 'boolean'],
            'equipment_items' => [Rule::requiredIf($request->boolean('needs_equipment')), 'array'],
            'equipment_items.*.lab_equipment_id' => ['nullable', 'integer', 'exists:lab_equipment,id'],
            'equipment_items.*.quantity' => ['required_with:equipment_items', 'numeric', 'gt:0'],
            'equipment_items.*.item' => ['required_with:equipment_items', 'string', 'max:255'],
            'equipment_items.*.description' => ['nullable', 'string', 'max:255'],
            'needs_reagents' => ['required', 'boolean'],
            'reagent_items' => [Rule::requiredIf($request->boolean('needs_reagents')), 'array'],
            'reagent_items.*.lab_consumable_id' => ['nullable', 'integer', 'exists:lab_consumables,id'],
            'reagent_items.*.quantity' => ['required_with:reagent_items', 'numeric', 'gt:0'],
            'reagent_items.*.reagent' => ['required_with:reagent_items', 'string', 'max:255'],
            'reagent_items.*.sds_read' => ['required_with:reagent_items', 'accepted'],
        ]);

        if (! $data['needs_equipment'] && $data['needs_reagents']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'needs_reagents' => 'Complete the equipment/accountability step before requesting reagents, as required by the approved form sequence.',
            ]);
        }

        return $data;
    }
}
