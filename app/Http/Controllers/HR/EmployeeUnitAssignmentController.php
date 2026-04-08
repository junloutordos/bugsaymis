<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\OrgUnit\StoreAssignmentRequest;
use App\Models\HR\EmployeeUnitAssignment;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeUnitAssignmentController extends Controller
{
    /**
     * GET /hr/org/units/{unit}/assignments
     * List all assignments for a given unit (active + historical).
     */
    public function index(Request $request, OrganizationalUnit $unit): JsonResponse
    {
        $this->authorize('org.view');

        $query = $unit->assignments()
            ->with('user:id,name,badge_id,emp_category')
            ->orderByDesc('is_primary')
            ->orderByDesc('effective_date');

        if ($request->boolean('active_only')) {
            $query->whereNull('end_date');
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    /**
     * GET /hr/org/employees/{user}/assignments
     * All unit assignments for a specific employee.
     */
    public function forEmployee(Request $request, User $user): JsonResponse
    {
        $this->authorize('org.assign');

        $assignments = EmployeeUnitAssignment::where('user_id', $user->id)
            ->with('unit:id,name,code,type')
            ->orderByDesc('is_primary')
            ->orderByDesc('effective_date')
            ->get();

        return response()->json($assignments);
    }

    /**
     * POST /hr/org/assignments
     * Create a new employee-unit assignment.
     */
    public function store(StoreAssignmentRequest $request): JsonResponse
    {
        $assignment = DB::transaction(function () use ($request) {
            return EmployeeUnitAssignment::create($request->validated());
        });

        return response()->json(
            $assignment->load(['user:id,name,badge_id', 'unit:id,name,code']),
            201
        );
    }

    /**
     * PUT /hr/org/assignments/{assignment}
     * Update an existing assignment.
     */
    public function update(Request $request, EmployeeUnitAssignment $assignment): JsonResponse
    {
        $this->authorize('org.assign.manage');

        $data = $request->validate([
            'assignment_type'  => ['sometimes', 'required', 'in:organic,seconded,designated,concurrent,detailed'],
            'appointment_type' => ['nullable', 'in:permanent,temporary,casual,cos,job_order,secondment'],
            'position_title'   => ['nullable', 'string', 'max:255'],
            'item_number'      => ['nullable', 'string', 'max:50'],
            'is_primary'       => ['boolean'],
            'end_date'         => ['nullable', 'date'],
            'order_number'     => ['nullable', 'string', 'max:100'],
            'remarks'          => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($assignment, $data) {
            $assignment->update($data);
        });

        return response()->json($assignment->fresh(['user:id,name', 'unit:id,name,code']));
    }

    /**
     * DELETE /hr/org/assignments/{assignment}
     * Soft-delete (archive) an assignment.
     */
    public function destroy(Request $request, EmployeeUnitAssignment $assignment): JsonResponse
    {
        $this->authorize('org.assign.manage');

        DB::transaction(fn () => $assignment->delete());

        return response()->json(['message' => 'Assignment removed.']);
    }

    /**
     * PATCH /hr/org/assignments/{assignment}/end
     * End an open assignment by setting end_date = today (or provided date).
     */
    public function end(Request $request, EmployeeUnitAssignment $assignment): JsonResponse
    {
        $this->authorize('org.assign');

        $data = $request->validate([
            'end_date' => ['nullable', 'date'],
            'remarks'  => ['nullable', 'string'],
        ]);

        $assignment->update([
            'end_date' => $data['end_date'] ?? now()->toDateString(),
            'remarks'  => $data['remarks'] ?? $assignment->remarks,
        ]);

        return response()->json($assignment->fresh());
    }
}
