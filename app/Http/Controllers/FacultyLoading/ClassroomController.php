<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\Classroom;
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

        $query = Classroom::query();

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

        return Inertia::render('FacultyLoading/Classrooms/Index', [
            'classrooms' => $classrooms,
            'filters'    => $request->only(['search', 'classroom_type', 'available']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.classrooms');

        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'code'           => 'required|string|max:20|unique:classrooms,code',
            'classroom_type' => 'required|in:lecture,laboratory,science_lab,physics_lab,chemistry_lab,biology_lab,mathematics_lab,ict_lab,language_lab,seminar,gymnasium,other',
            'capacity'       => 'required|integer|min:1',
            'building'       => 'nullable|string|max:100',
            'floor'          => 'nullable|integer',
            'is_available'   => 'boolean',
            'remarks'        => 'nullable|string',
        ]);

        Classroom::create($data);

        return back()->with('success', 'Classroom created.');
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('faculty_loading.classrooms');

        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'code'           => "required|string|max:20|unique:classrooms,code,{$classroom->id}",
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
}
