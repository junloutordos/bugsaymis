<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\FacultyLoading\DesignationService;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DesignationController extends Controller
{
    public function __construct(
        private readonly DesignationService     $service,
        private readonly LoadComputationService $loads,
    ) {}

    // ── Reference data (categories + designations) ────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.setup');

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);

        // Pre-load all holders for the selected term, grouped by designation_id
        $holdersByDesig = $termId
            ? LoadAssignment::with('faculty:id,name')
                ->where('academic_term_id', $termId)
                ->whereNotNull('designation_id')
                ->get()
                ->groupBy('designation_id')
            : collect();

        $categories = DesignationCategory::with(['designations.workDistributionPlans:id', 'workDistributionPlans:id'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($cat) => [
                'id'           => $cat->id,
                'code'         => $cat->code,
                'name'         => $cat->name,
                'description'  => $cat->description,
                'sort_order'   => $cat->sort_order,
                'is_active'    => $cat->is_active,
                'plan_ids'     => $cat->workDistributionPlans->pluck('id')->toArray(),
                'designations' => $cat->designations->map(fn ($d) => [
                    'id'              => $d->id,
                    'code'            => $d->code,
                    'name'            => $d->name,
                    'description'     => $d->description,
                    'load_units'      => (float) $d->load_units,
                    'assignment_type' => $d->assignment_type ?? 'admin',
                    'requires_unit'   => $d->requires_unit,
                    'max_holders'     => $d->max_holders,
                    'sort_order'      => $d->sort_order,
                    'is_active'       => $d->is_active,
                    'section_id'      => $d->section_id,
                    'plan_ids'        => $d->workDistributionPlans->pluck('id')->toArray(),
                    // Current holders — from any module (AUH, adviser, supervisory, etc.)
                    'holders'       => ($holdersByDesig->get($d->id) ?? collect())
                        ->map(fn ($a) => [
                            'assignment_id' => $a->id,
                            'user_id'       => $a->user_id,
                            'user_name'     => $a->faculty?->name ?? '—',
                            'load_units'    => (float) $a->load_units,
                        ])
                        ->values()
                        ->all(),
                ]),
            ]);

        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $faculty = User::employees()->where('status', '<>', 'inactive')
            ->where(fn ($q) => $q->where('on_study_leave', false)->orWhereNull('on_study_leave'))
            ->orderBy('name')
            ->get(['id', 'name', 'position']);

        return Inertia::render('FacultyLoading/Designations/Index', [
            'categories'          => $categories,
            'terms'               => $terms,
            'faculty'             => $faculty,
            'plans'               => WorkDistributionPlan::orderBy('success_indicator')->get(['id', 'success_indicator', 'rated_by']),
            'teachingLoadPlanIds' => WorkDistributionPlan::where('load_source', 'teaching')->pluck('id'),
            'currentTerm'         => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'             => $request->only('term_id'),
        ]);
    }

    // ── Category CRUD ─────────────────────────────────────────────────────────

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $data = $request->validate([
            'code'        => 'required|string|max:30|unique:designation_categories,code',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        DesignationCategory::create($data);

        return back()->with('success', 'Designation category created.');
    }

    public function updateCategory(Request $request, DesignationCategory $category): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $data = $request->validate([
            'code'        => "required|string|max:30|unique:designation_categories,code,{$category->id}",
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(DesignationCategory $category): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        if ($category->designations()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete: category has designations.']);
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    // ── Designation CRUD ──────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $data = $request->validate([
            'designation_category_id' => 'required|exists:designation_categories,id',
            'code'                    => 'required|string|max:40|unique:designations,code',
            'name'                    => 'required|string|max:150',
            'description'             => 'nullable|string',
            'load_units'              => 'required|numeric|min:0',
            'assignment_type'         => 'required|in:teaching,research,admin,cocurricular,committee',
            'requires_unit'           => 'boolean',
            'max_holders'             => 'nullable|integer|min:1',
            'sort_order'              => 'nullable|integer|min:0',
            'is_active'               => 'boolean',
        ]);

        Designation::create($data);

        return back()->with('success', 'Designation created.');
    }

    public function update(Request $request, Designation $designation): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $data = $request->validate([
            'designation_category_id' => 'required|exists:designation_categories,id',
            'code'                    => "required|string|max:40|unique:designations,code,{$designation->id}",
            'name'                    => 'required|string|max:150',
            'description'             => 'nullable|string',
            'load_units'              => 'required|numeric|min:0',
            'assignment_type'         => 'required|in:teaching,research,admin,cocurricular,committee',
            'requires_unit'           => 'boolean',
            'max_holders'             => 'nullable|integer|min:1',
            'sort_order'              => 'nullable|integer|min:0',
            'is_active'               => 'boolean',
        ]);

        $oldUnits = (float) $designation->load_units;
        $designation->update($data);

        // Propagate load_units change to non-overridden assignments
        if ((float) $data['load_units'] !== $oldUnits) {
            $affectedLoadIds = LoadAssignment::where('designation_id', $designation->id)
                ->where('is_overridden', false)
                ->pluck('faculty_load_id')
                ->unique();

            LoadAssignment::where('designation_id', $designation->id)
                ->where('is_overridden', false)
                ->update(['load_units' => (float) $data['load_units']]);

            FacultyLoad::whereIn('id', $affectedLoadIds)->each(fn ($fl) => $this->loads->syncLoad($fl));
        }

        return back()->with('success', 'Designation updated.');
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        if ($designation->loadAssignments()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete: designation has existing load assignments.']);
        }

        $designation->delete();

        return back()->with('success', 'Designation deleted.');
    }

    // ── Link / replace this category's Work Distribution Plans ───────────────
    // Set once on the "mother" category record — every designation under it,
    // and every current and future holder of any of them, inherits these
    // same plans on their IPCR automatically, without re-tagging each
    // individual designation.

    public function syncCategoryPlans(Request $request, DesignationCategory $category): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $data = $request->validate([
            'plan_ids'   => 'nullable|array',
            'plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $category->workDistributionPlans()->sync($data['plan_ids'] ?? []);

        return back()->with('success', 'Work Distribution Plans updated for this category.');
    }

    // ── Link / replace one Designation's OWN Work Distribution Plans ─────────
    // Additional to whatever its category already tags — every current and
    // future holder of this specific designation inherits the union of both.

    public function syncPlans(Request $request, Designation $designation): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $data = $request->validate([
            'plan_ids'   => 'nullable|array',
            'plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $designation->workDistributionPlans()->sync($data['plan_ids'] ?? []);

        return back()->with('success', 'Work Distribution Plans updated for this designation.');
    }

    // ── Tag Work Distribution Plans for ALL teaching loads ────────────────────
    // Sets/clears WorkDistributionPlan.load_source = 'teaching' on the
    // selected plans — every faculty member with any teaching load
    // automatically inherits them on their IPCR (FacultyIPCRBaselineService
    // "framework plans"), replacing the per-subject auto-generated fallback.

    public function syncTeachingLoadPlans(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.setup');

        $data = $request->validate([
            'plan_ids'   => 'nullable|array',
            'plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $planIds = $data['plan_ids'] ?? [];

        WorkDistributionPlan::whereIn('id', $planIds)->update(['load_source' => 'teaching']);
        WorkDistributionPlan::where('load_source', 'teaching')->whereNotIn('id', $planIds)->update(['load_source' => null]);

        return back()->with('success', 'Work Distribution Plans updated for Teaching Load.');
    }

    // ── Assign / Revoke direct from Designations catalog page ────────────────

    /**
     * Assign a faculty member to a designation directly from the catalog page.
     * Finds or creates the FacultyLoad record for the given user + term.
     */
    public function assignDirect(Request $request, Designation $designation): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        if (str_starts_with($designation->code, 'AUH-')) {
            return back()->withErrors(['error' => 'Unit head designations are managed through the Academic Units module.']);
        }

        if ($designation->section_id !== null) {
            return back()->withErrors(['error' => 'Section adviser designations are managed through the Sections module.']);
        }

        if (str_starts_with($designation->code, 'RES-')) {
            return back()->withErrors(['error' => 'Research designations are managed through the Research Advisories module.']);
        }

        $data = $request->validate([
            'user_id'          => 'required|exists:users,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'override_units'   => 'nullable|numeric|min:0',
        ]);

        $term = AcademicTerm::with('schoolYear')->findOrFail($data['academic_term_id']);

        $load = $this->loads->findOrCreateFacultyLoad(
            $data['user_id'],
            $term->school_year_id,
            $data['academic_term_id']
        );

        if ($load->is_locked) {
            return back()->withErrors(['error' => 'This faculty load record is locked and cannot be modified.']);
        }

        $result = $this->service->assign(
            $load,
            $designation,
            isset($data['override_units']) ? (float) $data['override_units'] : null,
            null,
            Auth::id()
        );

        if (! $result['ok']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Revoke a designation assignment directly from the catalog page.
     */
    public function revokeDirect(LoadAssignment $assignment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $assignment->load('designation');
        if ($assignment->designation && str_starts_with($assignment->designation->code, 'AUH-')) {
            return back()->withErrors(['error' => 'Unit head designations are managed through the Academic Units module.']);
        }

        if ($assignment->designation && $assignment->designation->section_id !== null) {
            return back()->withErrors(['error' => 'Section adviser designations are managed through the Sections module.']);
        }

        if ($assignment->designation && str_starts_with($assignment->designation->code, 'RES-')) {
            return back()->withErrors(['error' => 'Research designations are managed through the Research Advisories module.']);
        }

        $result = $this->service->revoke($assignment);

        if (! $result['ok']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    // ── Assign / Revoke (used from FacultyLoad detail page) ──────────────────

    public function assign(Request $request, FacultyLoad $facultyLoad): RedirectResponse
    {
        $this->authorize('faculty_loading.load_assignments');

        $data = $request->validate([
            'designation_id'   => 'required|exists:designations,id',
            'override_units'   => 'nullable|numeric|min:0',
            'academic_unit_id' => 'nullable|exists:academic_units,id',
        ]);

        $designation = Designation::findOrFail($data['designation_id']);

        if (str_starts_with($designation->code, 'AUH-')) {
            return back()->withErrors(['error' => 'Unit head designations are managed through the Academic Units module.']);
        }

        if ($designation->section_id !== null) {
            return back()->withErrors(['error' => 'Section adviser designations are managed through the Sections module.']);
        }

        if (str_starts_with($designation->code, 'RES-')) {
            return back()->withErrors(['error' => 'Research designations are managed through the Research Advisories module.']);
        }

        $result = $this->service->assign(
            $facultyLoad,
            $designation,
            $data['override_units'] ?? null,
            $data['academic_unit_id'] ?? null,
            $request->user()->id
        );

        if (! $result['ok']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function revoke(LoadAssignment $assignment): RedirectResponse
    {
        $this->authorize('faculty_loading.load_assignments');

        $assignment->load('designation');
        if ($assignment->designation && str_starts_with($assignment->designation->code, 'AUH-')) {
            return back()->withErrors(['error' => 'Unit head designations are managed through the Academic Units module.']);
        }

        if ($assignment->designation && $assignment->designation->section_id !== null) {
            return back()->withErrors(['error' => 'Section adviser designations are managed through the Sections module.']);
        }

        if ($assignment->designation && str_starts_with($assignment->designation->code, 'RES-')) {
            return back()->withErrors(['error' => 'Research designations are managed through the Research Advisories module.']);
        }

        $result = $this->service->revoke($assignment);

        if (! $result['ok']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }
}
