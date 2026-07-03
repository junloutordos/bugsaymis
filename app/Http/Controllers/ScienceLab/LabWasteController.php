<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\ScienceLab\LabWasteLog;
use App\Models\ScienceLab\ScienceLabProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LabWasteController extends Controller
{
    public const TYPES = ['chemical', 'biological', 'sharps', 'general', 'other'];

    public function index(Request $request)
    {
        $logs = LabWasteLog::with(['room:id,name', 'disposedBy:id,name'])
            ->when($request->string('waste_type')->value(), fn ($q, $t) => $q->where('waste_type', $t))
            ->orderByDesc('waste_date')->limit(300)->get();

        return Inertia::render('ScienceLab/Waste', [
            'logs'     => $logs,
            'labRooms' => $this->labRooms(),
            'types'    => self::TYPES,
            'filters'  => $request->only('waste_type'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['disposed_by_id'] = Auth::id();
        LabWasteLog::create($data);

        return back()->with('success', 'Waste disposal logged.');
    }

    public function update(Request $request, LabWasteLog $log)
    {
        $log->update($this->validated($request));

        return back()->with('success', 'Waste log updated.');
    }

    public function destroy(LabWasteLog $log)
    {
        $log->delete();

        return back()->with('success', 'Waste log removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'room_id'         => ['nullable', 'integer', 'exists:rooms,id'],
            'unit'            => ['nullable', 'string', 'max:100'],
            'waste_date'      => ['required', 'date'],
            'waste_type'      => ['required', Rule::in(self::TYPES)],
            'description'     => ['required', 'string', 'max:255'],
            'quantity'        => ['nullable', 'string', 'max:100'],
            'disposal_method' => ['nullable', 'string', 'max:200'],
            'remarks'         => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function labRooms()
    {
        $ids = ScienceLabProfile::pluck('room_id');

        return Room::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]);
    }
}
