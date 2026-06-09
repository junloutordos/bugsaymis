<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\FacultyLoading\HeadAdvisoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisoryController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.manage');

        $currentSy    = SchoolYear::where('is_current', true)->first();
        $schoolYearId = (int) $request->input('school_year_id', $currentSy?->id);

        $sy   = SchoolYear::find($schoolYearId);
        $term = $sy
            ? (AcademicTerm::where('school_year_id', $sy->id)->where('is_current', true)->first()
               ?? AcademicTerm::where('school_year_id', $sy->id)->orderBy('start_date')->first())
            : null;

        // All supervisory designations
        $category = DesignationCategory::where('code', 'SUPV')->first();

        $designations = $category
            ? Designation::where('designation_category_id', $category->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
            : collect();

        // Current assignment per designation, scoped by school year rather than term —
        // supervisory positions (SUPV) are year-level roles, not per-semester.
        $assignedMap = [];
        if ($term) {
            $assignments = LoadAssignment::with('faculty:id,name')
                ->whereIn('designation_id', $designations->pluck('id'))
                ->where('school_year_id', $sy->id)
                ->get();

            foreach ($assignments as $a) {
                $assignedMap[$a->designation_id] = [
                    'user_id'       => $a->user_id,
                    'user_name'     => $a->faculty?->name,
                    'assignment_id' => $a->id,
                    'load_units'    => (float) $a->load_units,
                    'source'        => 'fl',
                ];
            }
        }

        // Import suggestions: pull current chiefs from the divisions table
        // Map designation code → division acronym fragment
        $divisionImports = $this->getDivisionImports();

        $positions = $designations->map(function (Designation $d) use ($assignedMap, $divisionImports) {
            $assigned = $assignedMap[$d->id] ?? null;

            // Suggest from HR divisions if not yet assigned in FL
            $suggestion = null;
            if (! $assigned && isset($divisionImports[$d->code])) {
                $suggestion = $divisionImports[$d->code];
            }

            return [
                'id'          => $d->id,
                'code'        => $d->code,
                'name'        => $d->name,
                'load_units'  => (float) $d->load_units,
                'max_holders' => $d->max_holders,
                'assigned'    => $assigned,
                'suggestion'  => $suggestion,
            ];
        });

        $users = User::where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name']);

        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);

        return Inertia::render('FacultyLoading/Supervisory/Index', [
            'positions'           => $positions,
            'users'               => $users,
            'schoolYear'          => $sy ? ['id' => $sy->id, 'name' => $sy->name] : null,
            'term'                => $term ? ['id' => $term->id, 'name' => $term->name] : null,
            'schoolYears'         => $schoolYears,
            'currentSchoolYearId' => $schoolYearId,
        ]);
    }

    // ── Assign ────────────────────────────────────────────────────────────────

    public function assign(Request $request, Designation $designation, HeadAdvisoryService $advisory): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $category = $designation->category;
        if (! $category || $category->code !== 'SUPV') {
            return back()->withErrors(['error' => 'Invalid supervisory designation.']);
        }

        // Find current holder for this designation in the active school year
        $sy   = SchoolYear::where('is_current', true)->first();
        $term = $sy
            ? (AcademicTerm::where('school_year_id', $sy->id)->where('is_current', true)->first()
               ?? AcademicTerm::where('school_year_id', $sy->id)->orderBy('start_date')->first())
            : null;

        $oldUserId = null;
        if ($sy) {
            $oldUserId = LoadAssignment::where('designation_id', $designation->id)
                ->where('school_year_id', $sy->id)
                ->value('user_id');
        }

        $advisory->syncDesignationAssignment($designation, $oldUserId, (int) $data['user_id']);

        return back()->with('success', "{$designation->name} assigned successfully.");
    }

    // ── Remove ────────────────────────────────────────────────────────────────

    public function remove(Designation $designation, HeadAdvisoryService $advisory): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $sy = SchoolYear::where('is_current', true)->first();

        $oldUserId = $sy
            ? LoadAssignment::where('designation_id', $designation->id)
                ->where('school_year_id', $sy->id)
                ->value('user_id')
            : null;

        if ($oldUserId) {
            $advisory->syncDesignationAssignment($designation, $oldUserId, null);
        }

        return back()->with('success', "{$designation->name} assignment removed.");
    }

    // ── Import from HR divisions ──────────────────────────────────────────────

    public function importFromDivisions(HeadAdvisoryService $advisory): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $imports  = $this->getDivisionImports();
        $imported = 0;

        $sy = SchoolYear::where('is_current', true)->first();

        foreach ($imports as $code => $suggestion) {
            if (! $suggestion['user_id']) {
                continue;
            }

            $designation = Designation::where('code', $code)->first();
            if (! $designation) {
                continue;
            }

            // Skip if already assigned in FL
            $alreadyAssigned = $sy && LoadAssignment::where('designation_id', $designation->id)
                ->where('school_year_id', $sy->id)
                ->exists();

            if (! $alreadyAssigned) {
                $advisory->syncDesignationAssignment($designation, null, $suggestion['user_id']);
                $imported++;
            }
        }

        return back()->with('success', "Imported {$imported} assignment(s) from HR divisions.");
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Returns map of [designation_code => [user_id, user_name, division_name]]
     * by reading the current division chiefs from the HR divisions table.
     */
    private function getDivisionImports(): array
    {
        $map = [];

        // SUP-CID → CID (Curriculum and Instructions Division)
        $cid = Division::where('acronym', 'CID')->where('status', 'active')->first();
        if ($cid && $cid->division_chief_id) {
            $chief = User::find($cid->division_chief_id, ['id', 'name']);
            $map['SUP-CID'] = [
                'user_id'       => $chief?->id,
                'user_name'     => $chief?->name,
                'division_name' => $cid->division_name,
            ];
        }

        // SUP-SSD → SSD (Student Services Division)
        $ssd = Division::where('acronym', 'SSD')->where('status', 'active')->first();
        if ($ssd && $ssd->division_chief_id) {
            $chief = User::find($ssd->division_chief_id, ['id', 'name']);
            $map['SUP-SSD'] = [
                'user_id'       => $chief?->id,
                'user_name'     => $chief?->name,
                'division_name' => $ssd->division_name,
            ];
        }

        return $map;
    }
}
