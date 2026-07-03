<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\ScienceLab\LabEquipment;
use App\Models\ScienceLab\ScienceLabProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LabEquipmentController extends Controller
{
    public const STATUSES = ['active', 'under_repair', 'for_calibration', 'out_of_service', 'for_disposal', 'disposed'];
    public const FREQUENCIES = ['Monthly', 'Quarterly', 'Semiannual', 'Yearly'];

    public function index(Request $request)
    {
        $query = LabEquipment::with('room:id,name');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q
                ->where('description', 'like', "%{$search}%")
                ->orWhere('property_no', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%"));
        }
        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }
        if ($roomId = $request->integer('room_id')) {
            $query->where('room_id', $roomId);
        }

        $equipment = $query->orderBy('description')->get()->map(fn ($e) => [
            'id' => $e->id,
            'property_no' => $e->property_no,
            'description' => $e->description,
            'model' => $e->model,
            'serial_number' => $e->serial_number,
            'room' => $e->room?->name,
            'room_id' => $e->room_id,
            'unit' => $e->unit,
            'category' => $e->category,
            'date_acquired' => optional($e->date_acquired)->toDateString(),
            'unit_cost' => $e->unit_cost,
            'status' => $e->status,
            'calibration_required' => $e->calibration_required,
            'calibration_frequency' => $e->calibration_frequency,
            'pm_required' => $e->pm_required,
            'pm_frequency' => $e->pm_frequency,
            'remarks' => $e->remarks,
        ]);

        return Inertia::render('ScienceLab/Equipment', [
            'equipment'   => $equipment,
            'labRooms'    => $this->labRooms(),
            'statuses'    => self::STATUSES,
            'frequencies' => self::FREQUENCIES,
            'filters'     => $request->only('search', 'status', 'room_id'),
        ]);
    }

    public function show(LabEquipment $equipment)
    {
        $equipment->load([
            'room:id,name',
            'serviceLogs' => fn ($q) => $q->latest('log_date'),
            'serviceLogs.inputtedBy:id,name',
            'calibrationEvents' => fn ($q) => $q->latest('due_date'),
            'repairTickets' => fn ($q) => $q->latest('date_reported'),
        ]);

        return Inertia::render('ScienceLab/EquipmentShow', [
            'equipment' => $equipment,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();
        LabEquipment::create($data);

        return back()->with('success', 'Equipment added to the registry.');
    }

    public function update(Request $request, LabEquipment $equipment)
    {
        $equipment->update($this->validated($request));

        return back()->with('success', 'Equipment updated.');
    }

    public function destroy(LabEquipment $equipment)
    {
        $equipment->delete();

        return back()->with('success', 'Equipment removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'property_no'   => ['nullable', 'string', 'max:100'],
            'description'   => ['required', 'string', 'max:255'],
            'model'         => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'room_id'       => ['nullable', 'integer', 'exists:rooms,id'],
            'unit'          => ['nullable', 'string', 'max:100'],
            'category'      => ['nullable', 'string', 'max:100'],
            'date_acquired' => ['nullable', 'date'],
            'unit_cost'     => ['nullable', 'numeric', 'min:0'],
            'status'        => ['required', Rule::in(self::STATUSES)],
            'calibration_required'  => ['boolean'],
            'calibration_frequency' => ['nullable', Rule::in(self::FREQUENCIES)],
            'pm_required'   => ['boolean'],
            'pm_frequency'  => ['nullable', Rule::in(self::FREQUENCIES)],
            'remarks'       => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function labRooms()
    {
        $ids = ScienceLabProfile::pluck('room_id');

        return Room::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]);
    }
}
