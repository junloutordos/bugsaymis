<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\OrgUnit\StoreOrgUnitRequest;
use App\Http\Requests\HR\OrgUnit\UpdateOrgUnitRequest;
use App\Models\Division;
use App\Models\Office;
use App\Models\OrganizationalUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Collect linked division_ids and office_ids from org units
        $linkedDivisionIds = OrganizationalUnit::whereNotNull('division_id')->pluck('division_id')->toArray();
        $linkedOfficeIds   = OrganizationalUnit::whereNotNull('office_id')->pluck('office_id')->toArray();

        $divisions = Division::with(['employees:id,name,division_id'])
            ->orderBy('id')
            ->get()
            ->map(fn ($d) => [
                'id'           => $d->id,
                'name'         => $d->division_name,
                'acronym'      => $d->acronym,
                'status'       => $d->status,
                'is_linked'    => in_array($d->id, $linkedDivisionIds),
                'offices'      => Office::where('division_id', $d->id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($o) => [
                        'id'        => $o->id,
                        'name'      => $o->name,
                        'is_linked' => in_array($o->id, $linkedOfficeIds),
                    ])
                    ->values(),
            ]);

        return Inertia::render('HR/OrgStructure/Index', [
            'tree'            => fn () => OrganizationalUnit::getFullTree(onlyActive: ! $includeInactive),
            'includeInactive' => $includeInactive,
            'types'           => OrganizationalUnit::TYPES,
            'divisions'       => $divisions,
            'can'             => [
                'create'     => $request->user()->can('org.units.create'),
                'update'     => $request->user()->can('org.units.update'),
                'delete'     => $request->user()->can('org.units.delete'),
                'assign'     => $request->user()->can('org.assign'),
                'heads'      => $request->user()->can('org.heads.manage'),
                'export'     => $request->user()->can('org.export'),
                'reports'    => $request->user()->can('org.reports'),
                'versions'   => $request->user()->can('org.versions.view'),
                'sync'       => $request->user()->isSuperAdmin(),
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
            'headDesignations' => fn ($q) => $q->with('user:id,name')->orderByDesc('effective_date')->limit(20),
        ]);

        // ── Employees from users table via division_id / office_id linkage ──────
        $employeeQuery = \App\Models\User::where('status', '!=', 'inactive')
            ->whereNotNull('emp_category')
            ->where('emp_category', '!=', '')
            ->orderBy('name');

        if ($unit->office_id) {
            // Office/unit node — show employees in that specific office
            $employeeQuery->where('office_id', $unit->office_id);
        } elseif ($unit->division_id) {
            // Division node — show all employees in the division (with their office)
            $employeeQuery->where('division_id', $unit->division_id)
                ->with('office:id,name');
        } else {
            // Root / committee / unlinked — fallback to empty
            $employeeQuery->whereRaw('0 = 1');
        }

        $employees = $employeeQuery
            ->get(['id', 'name', 'badge_id', 'emp_category', 'position', 'sex', 'division_id', 'office_id'])
            ->map(fn ($e) => [
                'id'           => $e->id,
                'name'         => $e->name,
                'badge_id'     => $e->badge_id,
                'emp_category' => $e->emp_category,
                'position'     => $e->position,
                'sex'          => $e->sex,
                'office'       => $e->office?->name,
            ]);

        return Inertia::render('HR/OrgStructure/Show', [
            'unit'       => $unit,
            'breadcrumb' => $unit->breadcrumb,
            'employees'  => $employees,
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

    // ── Sync from legacy divisions / offices ───────────────────────────────────

    /**
     * POST /hr/org/sync-legacy
     *
     * Rebuilds the organizational_units tree from the canonical `divisions`
     * and `offices` tables, preserving the PSHS-CRC root node.
     *
     * Strategy:
     *  1. Find / create the root node (PSHS-CRC).
     *  2. For each Division: upsert an org unit linked via division_id.
     *  3. For each Office inside a division: upsert an org unit linked via office_id,
     *     parented to its division's org unit.
     *  4. Soft-delete any org unit that has no division_id / office_id AND is not
     *     the root node (orphaned custom units are archived, not deleted).
     */
    public function syncFromLegacy(Request $request): \Illuminate\Http\RedirectResponse
    {
        if (! Auth::user()->isSuperAdmin()) {
            abort(403, 'Only super-admins can sync the org structure.');
        }

        DB::transaction(function () {
            // ── Step 1: Ensure root node ─────────────────────────────────────
            $root = OrganizationalUnit::withTrashed()
                ->where('depth', 0)
                ->whereNull('parent_id')
                ->first();

            if (! $root) {
                $root = OrganizationalUnit::create([
                    'code'        => 'PSHS-CRC',
                    'name'        => 'Philippine Science High School – Caraga Regional Campus',
                    'short_name'  => 'PSHS-CRC',
                    'type'        => OrganizationalUnit::TYPE_INSTITUTION,
                    'is_active'   => true,
                    'order_index' => 0,
                ]);
            } else {
                if ($root->trashed()) {
                    $root->restore();
                }
            }

            // ── Step 2: Upsert org units for each Division ───────────────────
            $divisions = Division::orderBy('id')->get();
            $order     = 1;

            foreach ($divisions as $division) {
                // Match by division_id first, then fallback to acronym code
                $unit = OrganizationalUnit::withTrashed()
                    ->where('division_id', $division->id)
                    ->first()
                    ?? OrganizationalUnit::withTrashed()
                        ->where('code', strtoupper($division->acronym))
                        ->whereNull('division_id')
                        ->first();

                $attrs = [
                    'name'        => $division->division_name,
                    'code'        => strtoupper($division->acronym),
                    'short_name'  => $division->acronym,
                    'type'        => OrganizationalUnit::TYPE_DIVISION,
                    'parent_id'   => $root->id,
                    'division_id' => $division->id,
                    'is_active'   => $division->status === 'active',
                    'order_index' => $order++,
                ];

                if ($unit) {
                    if ($unit->trashed()) {
                        $unit->restore();
                    }
                    $unit->fill($attrs)->save();
                } else {
                    $unit = OrganizationalUnit::create($attrs);
                }

                // ── Step 3: Upsert org units for each Office in this Division ─
                $offices   = Office::where('division_id', $division->id)->orderBy('id')->get();
                $offOrder  = 1;

                foreach ($offices as $office) {
                    $oUnit = OrganizationalUnit::withTrashed()
                        ->where('office_id', $office->id)
                        ->first()
                        ?? OrganizationalUnit::withTrashed()
                            ->where('name', $office->name)
                            ->where('parent_id', $unit->id)
                            ->whereNull('office_id')
                            ->first();

                    // Generate a safe code from the office name
                    $code = strtoupper(
                        preg_replace('/[^A-Z0-9\-]/', '', str_replace(' ', '-', strtoupper($office->name)))
                    );
                    $code = substr($code, 0, 30) ?: 'OFF-' . $office->id;

                    $oAttrs = [
                        'name'        => $office->name,
                        'code'        => $code,
                        'short_name'  => $code,
                        'type'        => OrganizationalUnit::TYPE_UNIT,
                        'parent_id'   => $unit->id,
                        'office_id'   => $office->id,
                        'division_id' => $division->id,
                        'is_active'   => true,
                        'order_index' => $offOrder++,
                    ];

                    if ($oUnit) {
                        if ($oUnit->trashed()) {
                            $oUnit->restore();
                        }
                        $oUnit->fill($oAttrs)->save();
                    } else {
                        OrganizationalUnit::create($oAttrs);
                    }
                }
            }

            // ── Step 4: Archive orphaned custom units (not root, no linkage) ─
            OrganizationalUnit::whereNull('division_id')
                ->whereNull('office_id')
                ->where('id', '!=', $root->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);

            $this->bustTreeCache();
        });

        return back()->with('success', 'Organizational structure synchronized from Divisions and Offices.');
    }

    // ── Reports ────────────────────────────────────────────────────────────────

    /**
     * GET /hr/org/reports
     * Org structure statistics page.
     */
    public function reports(Request $request): Response
    {
        $this->authorize('org.reports');

        // ── Employee roster from users table (division_id / office_id) ──────────
        $employees = \App\Models\User::where('status', '!=', 'inactive')
            ->whereNotNull('division_id')
            ->whereNotNull('emp_category')
            ->where('emp_category', '!=', '')
            ->with([
                'division:id,division_name,acronym',
                'office:id,name,division_id',
            ])
            ->orderBy('name')
            ->get([
                'id', 'name', 'badge_id', 'emp_category',
                'position', 'sex', 'division_id', 'office_id', 'status',
            ]);

        // Group: division → office → employees
        $divisions = \App\Models\Division::where('status', 'active')
            ->with(['divisionchief:id,name,position'])
            ->orderBy('id')
            ->get();

        $officesAll = \App\Models\Office::orderBy('division_id')->orderBy('name')->get();

        $roster = $divisions->map(function ($div) use ($employees, $officesAll) {
            $divEmployees = $employees->where('division_id', $div->id);

            $offices = $officesAll->where('division_id', $div->id)->map(function ($off) use ($divEmployees) {
                $offEmp = $divEmployees->where('office_id', $off->id)->values();
                return [
                    'id'        => $off->id,
                    'name'      => $off->name,
                    'employees' => $offEmp->map(fn ($e) => [
                        'id'           => $e->id,
                        'name'         => $e->name,
                        'badge_id'     => $e->badge_id,
                        'emp_category' => $e->emp_category,
                        'position'     => $e->position,
                        'sex'          => $e->sex,
                    ])->values(),
                ];
            })->values();

            // Employees with no office assignment under this division
            $unassigned = $divEmployees->whereNull('office_id')->values()->map(fn ($e) => [
                'id'           => $e->id,
                'name'         => $e->name,
                'badge_id'     => $e->badge_id,
                'emp_category' => $e->emp_category,
                'position'     => $e->position,
                'sex'          => $e->sex,
            ])->values();

            return [
                'id'           => $div->id,
                'name'         => $div->division_name,
                'acronym'      => $div->acronym,
                'chief'        => $div->divisionchief?->name,
                'chief_pos'    => $div->divisionchief?->position,
                'total'        => $divEmployees->count(),
                'offices'      => $offices,
                'unassigned'   => $unassigned,
            ];
        });

        // ── Summary stats ────────────────────────────────────────────────────────
        $byCategory = $employees->groupBy('emp_category')->map->count();
        $bySex      = $employees->groupBy('sex')->map->count();

        return Inertia::render('HR/OrgStructure/Reports', [
            'roster'      => $roster,
            'totalCount'  => $employees->count(),
            'byCategory'  => $byCategory,
            'bySex'       => $bySex,
            'generatedAt' => now()->toDateTimeString(),
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function bustTreeCache(): void
    {
        Cache::forget('org.tree.active');
        Cache::forget('org.tree.all');
    }
}
