<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Office;
use App\Models\User;
use App\Services\FacultyLoading\HeadAdvisoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.setup');

        $currentSy    = SchoolYear::where('is_current', true)->first();
        $schoolYearId = (int) $request->input('school_year_id', $currentSy?->id);

        $units = AcademicUnit::with('head')
            ->where('school_year_id', $schoolYearId)
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

        $facultyOptions = User::employees()->where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name']);

        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);

        return Inertia::render('FacultyLoading/AcademicUnits/Index', [
            'units'               => $units,
            'facultyOptions'      => $facultyOptions,
            'schoolYears'         => $schoolYears,
            'currentSchoolYearId' => $schoolYearId,
        ]);
    }

    public function store(Request $request, HeadAdvisoryService $advisory): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $schoolYearId = (int) $request->input('school_year_id',
            SchoolYear::where('is_current', true)->value('id')
        );

        $data = $request->validate([
            'school_year_id' => 'required|exists:school_years,id',
            'code'           => "required|string|max:20|unique:academic_units,code,NULL,id,school_year_id,{$schoolYearId}",
            'name'           => 'required|string|max:100',
            'unit_type'      => 'required|in:junior_high,senior_high,sst,admin,department',
            'head_user_id'   => 'nullable|exists:users,id',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'boolean',
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

        $schoolYearId = $academicUnit->school_year_id;

        $data = $request->validate([
            'code'         => "required|string|max:20|unique:academic_units,code,{$academicUnit->id},id,school_year_id,{$schoolYearId}",
            'name'         => 'required|string|max:100',
            'unit_type'    => 'required|in:junior_high,senior_high,sst,admin,department',
            'head_user_id' => 'nullable|exists:users,id',
            'sort_order'   => 'nullable|integer|min:0',
            'is_active'    => 'boolean',
        ]);

        $oldHeadId = $academicUnit->head_user_id ? (int) $academicUnit->head_user_id : null;
        $newHeadId = isset($data['head_user_id']) ? (int) $data['head_user_id'] : null;
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

    /**
     * Sync active academic units from the selected school year into the offices table.
     * Matches by name (case-insensitive) and updates offices.unit_head with the unit's head_user_id.
     */
    public function syncToOffices(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $schoolYearId = (int) $request->input('school_year_id',
            SchoolYear::where('is_current', true)->value('id')
        );

        $units = AcademicUnit::where('school_year_id', $schoolYearId)
            ->where('is_active', true)
            ->get(['name', 'head_user_id']);

        $cidDivisionId = \App\Models\Division::where('acronym', 'CID')->value('id');

        $synced  = 0;
        $created = 0;

        foreach ($units as $unit) {
            $lower    = strtolower(trim($unit->name));
            $affected = Office::where(function ($q) use ($lower) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', [$lower])
                  ->orWhereRaw('LOWER(TRIM(name)) = ?', [$lower . ' unit']);
            })->update(['unit_head' => $unit->head_user_id]);

            if ($affected > 0) {
                $synced += $affected;
            } else {
                Office::create([
                    'name'        => $unit->name . ' Unit',
                    'division_id' => $cidDivisionId,
                    'unit_head'   => $unit->head_user_id,
                ]);
                $created++;
            }
        }

        $msg = "{$synced} office(s) updated";
        if ($created > 0) {
            $msg .= ", {$created} office(s) created";
        }

        return back()->with('success', $msg . ' from academic units.');
    }

    /**
     * Copy all academic units from a source school year into a target school year.
     * AUH designations (global) are ensured via firstOrCreate — no duplicates.
     * Skips unit codes that already exist in the target year.
     */
    public function copyFromYear(Request $request, HeadAdvisoryService $advisory): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $data = $request->validate([
            'source_school_year_id' => 'required|exists:school_years,id',
            'target_school_year_id' => 'required|exists:school_years,id|different:source_school_year_id',
        ]);

        $sourceId = $data['source_school_year_id'];
        $targetId = $data['target_school_year_id'];

        $existing = AcademicUnit::where('school_year_id', $targetId)->pluck('code')->flip();

        $sources = AcademicUnit::where('school_year_id', $sourceId)->orderBy('sort_order')->get();

        $copied = 0;
        foreach ($sources as $u) {
            if ($existing->has($u->code)) {
                continue;
            }
            $newUnit = AcademicUnit::create([
                'school_year_id' => $targetId,
                'code'           => $u->code,
                'name'           => $u->name,
                'unit_type'      => $u->unit_type,
                'head_user_id'   => $u->head_user_id,
                'sort_order'     => $u->sort_order,
                'is_active'      => $u->is_active,
            ]);

            // Ensure AUH-* designation exists (idempotent — no-op if already present)
            $advisory->ensureUnitDesignation($newUnit);

            $copied++;
        }

        $skipped = $sources->count() - $copied;
        $msg = "{$copied} academic unit(s) copied.";
        if ($skipped > 0) {
            $msg .= " {$skipped} skipped (codes already exist in target year).";
        }

        return back()->with('success', $msg);
    }
}
