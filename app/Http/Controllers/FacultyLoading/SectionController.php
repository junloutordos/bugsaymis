<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    // ── List sections ─────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.manage');

        $schoolYearId = $request->input('school_year_id');

        $sections = Section::with(['schoolYear', 'adviserUser:id,name'])
            ->when($schoolYearId, fn ($q) => $q->where('syid', $schoolYearId))
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get()
            ->map(fn ($s) => $this->mapSection($s));

        $schoolYears = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);
        $faculty     = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))->orderBy('name')->get(['id', 'name']);

        return Inertia::render('FacultyLoading/Sections/Index', [
            'sections'    => $sections,
            'schoolYears' => $schoolYears,
            'faculty'     => $faculty,
            'filters'     => $request->only(['school_year_id']),
        ]);
    }

    // ── Create a section ──────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'sectionname'  => 'required|string|max:100',
            'levelid'      => 'required|integer|min:7|max:12',
            'section_code' => 'nullable|string|max:30',
            'strand'       => 'nullable|string|max:50',
            'capacity'     => 'nullable|integer|min:1|max:60',
            'adviser'      => 'nullable|exists:users,id',
            'syid'         => 'required|exists:school_years,id',
            'is_active'    => 'boolean',
        ]);

        // Unique section name within the same school year and grade level
        $exists = Section::where('syid', $data['syid'])
            ->where('levelid', $data['levelid'])
            ->where('sectionname', $data['sectionname'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'sectionname' => "A Grade {$data['levelid']} section named '{$data['sectionname']}' already exists for this school year.",
            ]);
        }

        Section::create(array_merge($data, ['is_active' => $data['is_active'] ?? true]));

        return back()->with('success', 'Section created.');
    }

    // ── Update a section ──────────────────────────────────────────────────────

    public function update(Request $request, Section $section): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'sectionname'  => 'required|string|max:100',
            'levelid'      => 'required|integer|min:7|max:12',
            'section_code' => 'nullable|string|max:30',
            'strand'       => 'nullable|string|max:50',
            'capacity'     => 'nullable|integer|min:1|max:60',
            'adviser'      => 'nullable|exists:users,id',
            'is_active'    => 'boolean',
        ]);

        // Unique check, excluding current record
        $exists = Section::where('syid', $section->syid)
            ->where('levelid', $data['levelid'])
            ->where('sectionname', $data['sectionname'])
            ->where('id', '!=', $section->id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'sectionname' => "A Grade {$data['levelid']} section named '{$data['sectionname']}' already exists for this school year.",
            ]);
        }

        $section->update($data);

        return back()->with('success', 'Section updated.');
    }

    // ── Delete a section ──────────────────────────────────────────────────────

    public function destroy(Section $section): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        // Soft-disable instead of hard-delete if schedules reference this section
        $hasSchedules = $section->classSchedules()->exists();

        if ($hasSchedules) {
            $section->update(['is_active' => false]);
            return back()->with('success', 'Section deactivated (has existing schedules).');
        }

        $section->delete();

        return back()->with('success', 'Section deleted.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function mapSection(Section $s): array
    {
        return [
            'id'           => $s->id,
            'sectionname'  => $s->sectionname,
            'levelid'      => $s->levelid,
            'full_label'   => $s->full_label,
            'section_code' => $s->section_code,
            'strand'       => $s->strand,
            'capacity'     => $s->capacity,
            'is_active'    => $s->is_active,
            'adviser'      => $s->adviserUser ? ['id' => $s->adviserUser->id, 'name' => $s->adviserUser->name] : null,
            'school_year'  => $s->schoolYear ? ['id' => $s->schoolYear->id, 'name' => $s->schoolYear->name] : null,
        ];
    }
}
