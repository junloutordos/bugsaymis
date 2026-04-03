<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\OrgUnit\StoreOrgUnitRequest;
use App\Http\Requests\HR\OrgUnit\UpdateOrgUnitRequest;
use App\Models\OrganizationalUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrgUnitController extends Controller
{
    // ── Pages ──────────────────────────────────────────────────────────────────

    /**
     * Main org chart page (Inertia).
     */
    public function index(Request $request): Response
    {
        $this->authorize('org.view');

        $includeInactive = $request->boolean('include_inactive')
            && $request->user()->can('org.view_all');

        return Inertia::render('HR/OrgStructure/Index', [
            'tree'            => fn () => OrganizationalUnit::getFullTree(onlyActive: ! $includeInactive),
            'includeInactive' => $includeInactive,
            'types'           => OrganizationalUnit::TYPES,
            'can'             => [
                'create'  => $request->user()->can('org.units.create'),
                'update'  => $request->user()->can('org.units.update'),
                'delete'  => $request->user()->can('org.units.delete'),
                'assign'  => $request->user()->can('org.assign'),
                'heads'   => $request->user()->can('org.heads.manage'),
                'export'  => $request->user()->can('org.export'),
                'reports' => $request->user()->can('org.reports'),
                'versions'=> $request->user()->can('org.versions.view'),
            ],
        ]);
    }

    /**
     * Show a single unit detail page.
     */
    public function show(Request $request, OrganizationalUnit $unit): Response
    {
        $this->authorize('org.view');

        $unit->load([
            'parent:id,name,code',
            'activeChildren:id,name,code,type,is_active,order_index,parent_id',
            'currentHead' => fn ($q) => $q->with('user:id,name'),
            'activeAssignments' => fn ($q) => $q->with('user:id,name')->latest('effective_date')->limit(20),
            'headDesignations' => fn ($q) => $q->with('user:id,name')->orderByDesc('effective_date')->limit(20),
        ]);

        return Inertia::render('HR/OrgStructure/Show', [
            'unit'       => $unit,
            'breadcrumb' => $unit->breadcrumb,
            'can'        => [
                'assign'  => $request->user()->can('org.assign'),
                'heads'   => $request->user()->can('org.heads.manage'),
                'update'  => $request->user()->can('org.units.update'),
                'delete'  => $request->user()->can('org.units.delete'),
            ],
        ]);
    }

    // ── API / JSON endpoints ───────────────────────────────────────────────────

    /**
     * GET /hr/org/tree
     * Full tree as JSON — used by Vue components.
     */
    public function tree(Request $request): JsonResponse
    {
        $this->authorize('org.view');

        $includeInactive = $request->boolean('include_inactive')
            && $request->user()->can('org.view_all');

        $cacheKey = 'org.tree.' . ($includeInactive ? 'all' : 'active');

        $tree = Cache::remember($cacheKey, 120, fn () =>
            OrganizationalUnit::getFullTree(onlyActive: ! $includeInactive)
        );

        return response()->json($tree);
    }

    /**
     * GET /hr/org/units
     * Flat paginated list with optional filters.
     */
    public function list(Request $request): JsonResponse
    {
        $this->authorize('org.view');

        $query = OrganizationalUnit::query()
            ->with('parent:id,name,code')
            ->orderBy('depth')
            ->orderBy('order_index')
            ->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        } elseif (! $request->user()->can('org.view_all')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(fn ($q) =>
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search)
                  ->orWhere('short_name', 'like', $search)
            );
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 25))
        );
    }

    // ── CRUD ───────────────────────────────────────────────────────────────────

    /**
     * POST /hr/org/units
     */
    public function store(StoreOrgUnitRequest $request): JsonResponse
    {
        $unit = DB::transaction(function () use ($request) {
            return OrganizationalUnit::create($request->validated());
        });

        $this->bustTreeCache();

        return response()->json($unit->load('parent:id,name,code'), 201);
    }

    /**
     * PUT /hr/org/units/{unit}
     */
    public function update(UpdateOrgUnitRequest $request, OrganizationalUnit $unit): JsonResponse
    {
        DB::transaction(function () use ($request, $unit) {
            $unit->update($request->validated());
        });

        $this->bustTreeCache();

        return response()->json($unit->fresh(['parent:id,name,code']));
    }

    /**
     * DELETE /hr/org/units/{unit}
     */
    public function destroy(Request $request, OrganizationalUnit $unit): JsonResponse
    {
        $this->authorize('org.units.delete');

        // Prevent deletion if the unit has active employees
        if ($unit->activeAssignments()->exists()) {
            return response()->json([
                'message' => "Cannot delete \"{$unit->name}\": it has active employee assignments. Reassign or end those assignments first.",
            ], 422);
        }

        // Prevent deletion if it still has active children
        if ($unit->activeChildren()->exists()) {
            return response()->json([
                'message' => "Cannot delete \"{$unit->name}\": it still has active child units. Remove or archive them first.",
            ], 422);
        }

        DB::transaction(function () use ($unit) {
            $unit->delete();
        });

        $this->bustTreeCache();

        return response()->json(['message' => 'Unit archived successfully.']);
    }

    /**
     * POST /hr/org/units/{unit}/restore
     */
    public function restore(Request $request, int $unitId): JsonResponse
    {
        $this->authorize('org.units.manage');

        $unit = OrganizationalUnit::onlyTrashed()->findOrFail($unitId);
        $unit->restore();

        $this->bustTreeCache();

        return response()->json($unit);
    }

    /**
     * PATCH /hr/org/units/{unit}/move
     * Reparent a unit (changes parent_id with circular-reference guard).
     */
    public function move(Request $request, OrganizationalUnit $unit): JsonResponse
    {
        $this->authorize('org.units.update');

        $data = $request->validate([
            'parent_id'   => ['nullable', 'integer', 'exists:organizational_units,id'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($data['parent_id']) && $data['parent_id'] != $unit->parent_id) {
            try {
                $unit->guardCircularReference((int) $data['parent_id']);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        DB::transaction(function () use ($unit, $data) {
            $unit->update($data);
        });

        $this->bustTreeCache();

        return response()->json($unit->fresh());
    }

    // ── Reports ────────────────────────────────────────────────────────────────

    /**
     * GET /hr/org/reports
     * Org structure statistics page.
     */
    public function reports(Request $request): Response
    {
        $this->authorize('org.reports');

        $units = OrganizationalUnit::withCount([
            'activeAssignments',
            'children',
        ])->get();

        $byType = $units->groupBy('type')->map(fn ($g) => [
            'count'       => $g->count(),
            'active'      => $g->where('is_active', true)->count(),
            'employees'   => $g->sum('active_assignments_count'),
        ]);

        $topUnits = $units->where('is_active', true)
            ->sortByDesc('active_assignments_count')
            ->take(10)
            ->values()
            ->map(fn ($u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'code'           => $u->code,
                'type'           => $u->type,
                'employee_count' => $u->active_assignments_count,
            ]);

        return Inertia::render('HR/OrgStructure/Reports', [
            'stats' => [
                'total_units'    => $units->count(),
                'active_units'   => $units->where('is_active', true)->count(),
                'root_units'     => $units->whereNull('parent_id')->count(),
                'max_depth'      => $units->max('depth') ?? 0,
                'total_employees'=> $units->sum('active_assignments_count'),
                'by_type'        => $byType,
                'top_units'      => $topUnits,
            ],
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function bustTreeCache(): void
    {
        Cache::forget('org.tree.active');
        Cache::forget('org.tree.all');
    }
}
