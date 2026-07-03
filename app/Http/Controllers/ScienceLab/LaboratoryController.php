<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\ScienceLab\ScienceLabProfile;
use App\Models\User;
use App\Traits\HandlesLabUploads;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LaboratoryController extends Controller
{
    use HandlesLabUploads;

    private const UNITS = ['Chemistry', 'Biology', 'Physics', 'Research', 'General'];

    public function index()
    {
        $profiles = ScienceLabProfile::with([
            'room:id,name,code,building_id,capacity',
            'room.building:id,name',
            'auh:id,name',
            'inCharge:id,name',
        ])->orderBy('unit')->get()->map(fn ($p) => [
            'id'         => $p->id,
            'room_id'    => $p->room_id,
            'room'       => $p->room?->name,
            'code'       => $p->room?->code,
            'building'   => $p->room?->building?->name,
            'capacity'   => $p->room?->capacity,
            'unit'       => $p->unit,
            'auh'        => $p->auh?->name,
            'auh_user_id'=> $p->auh_user_id,
            'in_charge'  => $p->inCharge?->name,
            'in_charge_user_id' => $p->in_charge_user_id,
            'has_safety_doc'    => (bool) $p->safety_procedure_path,
            'safety_procedure_path' => $p->safety_procedure_path,
            'status'     => $p->status,
            'remarks'    => $p->remarks,
        ]);

        // Rooms not yet profiled (available to designate as a science lab)
        $usedRoomIds = ScienceLabProfile::pluck('room_id');
        $availableRooms = Room::whereNotIn('id', $usedRoomIds)
            ->orderBy('name')->get(['id', 'name', 'code', 'capacity'])
            ->map(fn ($r) => ['id' => $r->id, 'label' => trim($r->name . ($r->code ? " ({$r->code})" : ''))]);

        return Inertia::render('ScienceLab/Laboratories', [
            'profiles'       => $profiles,
            'availableRooms' => $availableRooms,
            'units'          => self::UNITS,
            'staff'          => $this->staffOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, true);
        $path = $this->storeLabUpload($request->input('safety_procedure_base64'), 'lab/safety-procedures');

        ScienceLabProfile::create([
            'room_id'               => $data['room_id'],
            'unit'                  => $data['unit'],
            'auh_user_id'           => $data['auh_user_id'] ?? null,
            'in_charge_user_id'     => $data['in_charge_user_id'] ?? null,
            'safety_procedure_path' => $path,
            'status'                => $data['status'] ?? 'active',
            'remarks'               => $data['remarks'] ?? null,
        ]);

        // Tag the room so it is recognisable as a science laboratory.
        Room::where('id', $data['room_id'])->update(['room_type' => 'Science Laboratory']);

        return back()->with('success', 'Laboratory designated.');
    }

    public function update(Request $request, ScienceLabProfile $profile)
    {
        $data = $this->validatePayload($request, false);

        $profile->fill([
            'unit'              => $data['unit'],
            'auh_user_id'       => $data['auh_user_id'] ?? null,
            'in_charge_user_id' => $data['in_charge_user_id'] ?? null,
            'status'            => $data['status'] ?? $profile->status,
            'remarks'           => $data['remarks'] ?? null,
        ]);

        if ($path = $this->storeLabUpload($request->input('safety_procedure_base64'), 'lab/safety-procedures')) {
            $profile->safety_procedure_path = $path;
        }
        $profile->save();

        return back()->with('success', 'Laboratory updated.');
    }

    public function destroy(ScienceLabProfile $profile)
    {
        $profile->delete();

        return back()->with('success', 'Laboratory designation removed.');
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'room_id' => [
                Rule::requiredIf($creating), 'integer', 'exists:rooms,id',
                Rule::unique('science_lab_profiles', 'room_id'),
            ],
            'unit'              => ['required', Rule::in(self::UNITS)],
            'auh_user_id'       => ['nullable', 'integer', 'exists:users,id'],
            'in_charge_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status'            => ['nullable', Rule::in(['active', 'inactive'])],
            'remarks'           => ['nullable', 'string', 'max:1000'],
            'safety_procedure_base64' => ['nullable', 'string'],
        ]);
    }

    private function staffOptions()
    {
        return User::where('status', '<>', 'inactive')
            ->orderBy('name')->limit(1000)->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);
    }
}
