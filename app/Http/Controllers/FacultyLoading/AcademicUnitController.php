<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\User;
use App\Services\FacultyLoading\HeadAdvisoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicUnitController extends Controller
{
    public function index(): Response
    {
        $this->authorize('faculty_loading.setup');

        $units = AcademicUnit::with('head')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id'           => $u->id,
                'code'         => $u->code,
                'name'         => $u->name,
                'unit_type'    => $u->unit_type,
                'grade_band'   => $u->grade_band,
                'head_user_id' => $u->head_user_id,
                'head_name'    => $u->head?->name,
                'sort_order'   => $u->sort_order,
                'is_active'    => $u->is_active,
            ]);

        $facultyOptions = User::where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('FacultyLoading/AcademicUnits/Index', [
            'units'         => $units,
            'facultyOptions' => $facultyOptions,
        ]);
    }

    public function store(Request $request, HeadAdvisoryService $advisory): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $data = $request->validate([
            'code'         => 'required|string|max:20|unique:academic_units,code',
            'name'         => 'required|string|max:100',
            'unit_type'    => 'required|in:junior_high,senior_high,sst,admin,department',
            'head_user_id' => 'nullable|exists:users,id',
            'sort_order'   => 'nullable|integer|min:0',
            'is_active'    => 'boolean',
        ]);

        $unit = AcademicUnit::create($data);

        $advisory->ensureUnitDesignation($unit);

        if ($unit->head_user_id) {
            $advisory->syncUnitHead($unit, null);
        }

        return back()->with('success', 'Academic unit created.');
    }

    public function update(Request $request, AcademicUnit $academicUnit, HeadAdvisoryService $advisory): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $data = $request->validate([
            'code'         => "required|string|max:20|unique:academic_units,code,{$academicUnit->id}",
            'name'         => 'required|string|max:100',
            'unit_type'    => 'required|in:junior_high,senior_high,sst,admin,department',
            'head_user_id' => 'nullable|exists:users,id',
            'sort_order'   => 'nullable|integer|min:0',
            'is_active'    => 'boolean',
        ]);

        $oldHeadId = $academicUnit->head_user_id ? (int) $academicUnit->head_user_id : null;
        $newHeadId = $data['head_user_id'] ? (int) $data['head_user_id'] : null;
        $oldCode   = $academicUnit->code;
        $oldName   = $academicUnit->name;

        $academicUnit->update($data);

        $advisory->syncUnitDesignation($academicUnit->fresh(), $oldCode, $oldName);

        if ($oldHeadId !== $newHeadId) {
            $advisory->syncUnitHead($academicUnit->fresh(), $oldHeadId);
        }

        return back()->with('success', 'Academic unit updated.');
    }

    public function destroy(AcademicUnit $academicUnit): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        if ($academicUnit->subjects()->exists() || $academicUnit->vacancies()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete: academic unit has associated subjects or vacancies.']);
        }

        $academicUnit->delete();

        return back()->with('success', 'Academic unit deleted.');
    }
}
