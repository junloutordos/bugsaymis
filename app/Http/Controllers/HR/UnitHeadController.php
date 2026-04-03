<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\OrgUnit\StoreUnitHeadRequest;
use App\Models\HR\UnitHead;
use App\Models\OrganizationalUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitHeadController extends Controller
{
    /**
     * GET /hr/org/units/{unit}/heads
     * List head designations for a unit (current + historical).
     */
    public function index(Request $request, OrganizationalUnit $unit): JsonResponse
    {
        $this->authorize('org.view');

        $query = $unit->headDesignations()
            ->with('user:id,name,badge_id')
            ->orderByDesc('is_current')
            ->orderByDesc('effective_date');

        if ($request->boolean('current_only')) {
            $query->where('is_current', true)->whereNull('end_date');
        }

        return response()->json($query->get());
    }

    /**
     * GET /hr/org/heads — All current unit heads across all units.
     */
    public function allCurrent(Request $request): JsonResponse
    {
        $this->authorize('org.view');

        $heads = UnitHead::with([
                'user:id,name,badge_id',
                'unit:id,name,code,type',
            ])
            ->where('is_current', true)
            ->whereNull('end_date')
            ->whereNull('deleted_at')
            ->orderBy('unit_id')
            ->get();

        return response()->json($heads);
    }

    /**
     * POST /hr/org/heads
     * Designate a new unit head.
     * The model boot hook automatically closes the previous current head.
     */
    public function store(StoreUnitHeadRequest $request): JsonResponse
    {
        $head = DB::transaction(function () use ($request) {
            return UnitHead::create($request->validated());
        });

        return response()->json(
            $head->load(['user:id,name,badge_id', 'unit:id,name,code']),
            201
        );
    }

    /**
     * PUT /hr/org/heads/{head}
     * Update designation details (not for changing unit or user — create a new one instead).
     */
    public function update(Request $request, UnitHead $head): JsonResponse
    {
        $this->authorize('org.heads.manage');

        $data = $request->validate([
            'designation_title'      => ['nullable', 'string', 'max:255'],
            'is_acting'              => ['boolean'],
            'effective_date'         => ['nullable', 'date'],
            'end_date'               => ['nullable', 'date'],
            'designation_order'      => ['nullable', 'string', 'max:100'],
            'designation_order_date' => ['nullable', 'date'],
            'remarks'                => ['nullable', 'string'],
        ]);

        DB::transaction(fn () => $head->update($data));

        return response()->json($head->fresh(['user:id,name', 'unit:id,name,code']));
    }

    /**
     * DELETE /hr/org/heads/{head}
     * Soft-delete a head designation.
     */
    public function destroy(UnitHead $head): JsonResponse
    {
        $this->authorize('org.heads.manage');

        DB::transaction(fn () => $head->delete());

        return response()->json(['message' => 'Designation removed.']);
    }

    /**
     * PATCH /hr/org/heads/{head}/end
     * End a current head designation.
     */
    public function end(Request $request, UnitHead $head): JsonResponse
    {
        $this->authorize('org.heads.manage');

        $data = $request->validate([
            'end_date' => ['nullable', 'date'],
            'remarks'  => ['nullable', 'string'],
        ]);

        $head->update([
            'is_current' => false,
            'end_date'   => $data['end_date'] ?? now()->toDateString(),
            'remarks'    => $data['remarks'] ?? $head->remarks,
        ]);

        return response()->json($head->fresh());
    }
}
