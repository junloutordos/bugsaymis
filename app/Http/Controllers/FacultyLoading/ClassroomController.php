<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.classrooms');

        $currentSy    = SchoolYear::where('is_current', true)->first();
        $schoolYearId = (int) $request->input('school_year_id', $currentSy?->id);

        $query = Classroom::where('school_year_id', $schoolYearId);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                                       ->orWhere('code', 'like', "%{$s}%")
                                       ->orWhere('building', 'like', "%{$s}%"));
        }

        if ($request->filled('classroom_type') && $request->classroom_type !== 'all') {
            $query->where('classroom_type', $request->classroom_type);
        }

        if ($request->filled('available')) {
            $query->where('is_available', $request->boolean('available'));
        }

        $classrooms = $query->orderBy('building')->orderBy('name')->get()->map(fn ($c) => [
            'id'             => $c->id,
            'name'           => $c->name,
            'code'           => $c->code,
            'classroom_type' => $c->classroom_type,
            'capacity'       => $c->capacity,
            'building'       => $c->building,
            'floor'          => $c->floor,
            'is_available'   => $c->is_available,
            'remarks'        => $c->remarks,
            'nfc_uuid'       => $c->nfc_uuid,
            'nfc_url'        => $c->nfc_uuid ? url('/class-tap/' . $c->nfc_uuid) : null,
        ]);

        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);

        return Inertia::render('FacultyLoading/Classrooms/Index', [
            'classrooms'          => $classrooms,
            'schoolYears'         => $schoolYears,
            'currentSchoolYearId' => $schoolYearId,
            'filters'             => $request->only(['search', 'classroom_type', 'available', 'school_year_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.classrooms');

        $schoolYearId = (int) $request->input('school_year_id',
            SchoolYear::where('is_current', true)->value('id')
        );

        $data = $request->validate([
            'school_year_id' => 'nullable|exists:school_years,id',
            'name'           => 'required|string|max:100',
            'code'           => "required|string|max:20|unique:classrooms,code,NULL,id,school_year_id,{$schoolYearId}",
            'classroom_type' => 'required|in:lecture,laboratory,science_lab,physics_lab,chemistry_lab,biology_lab,mathematics_lab,ict_lab,language_lab,seminar,gymnasium,other',
            'capacity'       => 'required|integer|min:1',
            'building'       => 'nullable|string|max:100',
            'floor'          => 'nullable|integer',
            'is_available'   => 'boolean',
            'remarks'        => 'nullable|string',
        ]);

        // The uniqueness rule above is scoped to $schoolYearId (falls back to
        // the current school year when omitted) — save under that same value
        // so the row's actual scope always matches what was just checked.
        $data['school_year_id'] = $schoolYearId;

        Classroom::create($data);

        return back()->with('success', 'Classroom created.');
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('faculty_loading.classrooms');

        $schoolYearId = $classroom->school_year_id;

        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'code'           => "required|string|max:20|unique:classrooms,code,{$classroom->id},id,school_year_id,{$schoolYearId}",
            'classroom_type' => 'required|in:lecture,laboratory,science_lab,physics_lab,chemistry_lab,biology_lab,mathematics_lab,ict_lab,language_lab,seminar,gymnasium,other',
            'capacity'       => 'required|integer|min:1',
            'building'       => 'nullable|string|max:100',
            'floor'          => 'nullable|integer',
            'is_available'   => 'boolean',
            'remarks'        => 'nullable|string',
        ]);

        $classroom->update($data);

        return back()->with('success', 'Classroom updated.');
    }

    public function regenerateNfc(Classroom $classroom): RedirectResponse
    {
        $this->authorize('faculty_loading.classrooms');

        $classroom->update(['nfc_uuid' => Str::uuid()->toString()]);

        return back()->with('success', "NFC tag for \"{$classroom->name}\" regenerated. Reprogram the physical tag with the new URL.");
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $this->authorize('faculty_loading.classrooms');

        if ($classroom->classSchedules()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete: classroom has existing schedules.']);
        }

        $classroom->delete();

        return back()->with('success', 'Classroom deleted.');
    }

    /**
     * Copy all classrooms from a source school year into a target school year.
     * NFC UUIDs are copied so the same physical tags remain functional in the new year.
     * Skips any classroom code that already exists in the target year.
     */
    public function copyFromYear(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.classrooms');

        $data = $request->validate([
            'source_school_year_id' => 'required|exists:school_years,id',
            'target_school_year_id' => 'required|exists:school_years,id|different:source_school_year_id',
        ]);

        $sourceId = $data['source_school_year_id'];
        $targetId = $data['target_school_year_id'];

        $existing = Classroom::where('school_year_id', $targetId)->pluck('code')->flip();

        $sources = Classroom::where('school_year_id', $sourceId)->get();

        $copied = 0;
        foreach ($sources as $c) {
            if ($existing->has($c->code)) {
                continue;
            }
            Classroom::create([
                'school_year_id' => $targetId,
                'name'           => $c->name,
                'code'           => $c->code,
                'classroom_type' => $c->classroom_type,
                'capacity'       => $c->capacity,
                'building'       => $c->building,
                'floor'          => $c->floor,
                'room_id'        => $c->room_id,
                'is_available'   => $c->is_available,
                'remarks'        => $c->remarks,
                'nfc_uuid'       => $c->nfc_uuid, // preserve so physical tags still work
            ]);
            $copied++;
        }

        $skipped = $sources->count() - $copied;
        $msg = "{$copied} classroom(s) copied.";
        if ($skipped > 0) {
            $msg .= " {$skipped} skipped (codes already exist in target year).";
        }

        return back()->with('success', $msg);
    }
}
