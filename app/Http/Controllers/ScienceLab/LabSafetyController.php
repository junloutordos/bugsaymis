<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\ScienceLab\LabFirstAidCheck;
use App\Models\ScienceLab\LabConsumable;
use App\Models\ScienceLab\LabSafetyOrientation;
use App\Models\ScienceLab\LabSafetyProcedure;
use App\Models\ScienceLab\ScienceLabProfile;
use App\Models\FacultyLoading\SchoolYear;
use App\Traits\HandlesLabUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LabSafetyController extends Controller
{
    use HandlesLabUploads;

    public function index()
    {
        return Inertia::render('ScienceLab/Safety', [
            'procedures'   => LabSafetyProcedure::with(['room:id,name', 'determinedBy:id,name'])
                                ->orderByDesc('effective_date')->get(),
            'orientations' => LabSafetyOrientation::with(['room:id,name', 'conductedBy:id,name'])
                                ->orderByDesc('orientation_date')->limit(100)->get(),
            'firstAid'     => LabFirstAidCheck::with(['room:id,name', 'checkedBy:id,name'])
                                ->orderByDesc('check_date')->limit(100)->get(),
            'safetyDataSheets' => LabConsumable::with('room:id,name')
                                ->whereNotNull('sds_path')->where('status', '<>', 'inactive')
                                ->orderBy('name')->get(['id', 'name', 'type', 'room_id', 'cas_number', 'hazards', 'sds_path']),
            'labRooms'     => $this->labRooms(),
            'schoolYear'   => SchoolYear::where('is_current', true)->first(['id', 'name']),
        ]);
    }

    public function storeProcedure(Request $request)
    {
        $data = $request->validate([
            'room_id'        => ['nullable', 'integer', 'exists:rooms,id'],
            'unit'           => ['nullable', 'string', 'max:100'],
            'title'          => ['required', 'string', 'max:255'],
            'body'           => ['nullable', 'string'],
            'is_posted'      => ['boolean'],
            'effective_date' => ['nullable', 'date'],
            'document_base64'=> ['nullable', 'string'],
        ]);
        $data['determined_by_id'] = Auth::id();
        if ($path = $this->storeLabUpload($request->input('document_base64'), 'lab/safety-procedures')) {
            $data['document_path'] = $path;
        }
        unset($data['document_base64']);

        LabSafetyProcedure::create($data);

        return back()->with('success', 'Safety procedure recorded.');
    }

    public function updateProcedure(Request $request, LabSafetyProcedure $procedure)
    {
        $data = $request->validate([
            'room_id'        => ['nullable', 'integer', 'exists:rooms,id'],
            'unit'           => ['nullable', 'string', 'max:100'],
            'title'          => ['required', 'string', 'max:255'],
            'body'           => ['nullable', 'string'],
            'is_posted'      => ['boolean'],
            'effective_date' => ['nullable', 'date'],
            'status'         => ['nullable', 'in:active,inactive'],
            'document_base64'=> ['nullable', 'string'],
        ]);
        if ($path = $this->storeLabUpload($request->input('document_base64'), 'lab/safety-procedures')) {
            $data['document_path'] = $path;
        }
        unset($data['document_base64']);
        $procedure->update($data);

        return back()->with('success', 'Safety procedure updated.');
    }

    public function storeOrientation(Request $request)
    {
        $data = $request->validate([
            'room_id'             => ['nullable', 'integer', 'exists:rooms,id'],
            'orientation_date'    => ['required', 'date'],
            'grade_level_section' => ['nullable', 'string', 'max:100'],
            'topic'               => ['nullable', 'string', 'max:200'],
            'attendees'           => ['nullable', 'array'],
            'attendees.*'         => ['nullable', 'string', 'max:150'],
            'remarks'             => ['nullable', 'string', 'max:1000'],
            'document_base64'     => ['nullable', 'string'],
        ]);
        $data['school_year_id'] = SchoolYear::where('is_current', true)->value('id');
        $data['conducted_by_id'] = Auth::id();
        if ($path = $this->storeLabUpload($request->input('document_base64'), 'lab/safety-orientations')) {
            $data['document_path'] = $path;
        }
        unset($data['document_base64']);

        LabSafetyOrientation::create($data);

        return back()->with('success', 'Safety orientation logged.');
    }

    public function storeFirstAid(Request $request)
    {
        $data = $request->validate([
            'room_id'     => ['nullable', 'integer', 'exists:rooms,id'],
            'check_date'  => ['required', 'date'],
            'means_of_verification' => ['nullable', 'string', 'max:255'],
            'inclusion'   => ['nullable', 'string', 'max:255'],
            'document_base64' => ['nullable', 'string'],
            'items'       => ['nullable', 'array'],
            'is_complete' => ['boolean'],
            'remarks'     => ['nullable', 'string', 'max:1000'],
        ]);
        $data['checked_by_id'] = Auth::id();
        if ($path = $this->storeLabUpload($request->input('document_base64'), 'lab/first-aid-checks')) {
            $data['document_path'] = $path;
        }
        unset($data['document_base64']);

        LabFirstAidCheck::create($data);

        return back()->with('success', 'First-aid kit check recorded.');
    }

    private function labRooms()
    {
        $ids = ScienceLabProfile::pluck('room_id');

        return Room::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]);
    }
}
