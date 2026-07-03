<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkRequest;
use App\Models\WorkRequestPreRepairInspection;
use Illuminate\Http\Request;

class WorkRequestPreRepairInspectionController extends Controller
{
    public function save(Request $request, WorkRequest $workRequest)
    {
        $this->authorizeEdit($request, $workRequest);

        if ($workRequest->preRepairInspection?->status === 'noted') {
            return back()->withErrors(['inspection' => 'A noted pre-repair inspection can no longer be edited.']);
        }

        $data = $this->validatedInspection($request, false);
        $data = $this->withComputedTotals($data);
        $data['status'] = $workRequest->preRepairInspection?->status === 'noted'
            ? 'noted'
            : ($workRequest->preRepairInspection?->status ?? 'draft');
        $data['work_request_id'] = $workRequest->id;

        if (empty($data['inspector_id'])) {
            $data['inspector_id'] = $workRequest->assigned_user_id ?: $request->user()->id;
        }

        WorkRequestPreRepairInspection::updateOrCreate(
            ['work_request_id' => $workRequest->id],
            $data,
        );

        return back()->with('success', 'Pre-repair inspection saved.');
    }

    public function submit(Request $request, WorkRequest $workRequest)
    {
        $this->authorizeEdit($request, $workRequest);

        if ($workRequest->preRepairInspection?->status === 'noted') {
            return back()->withErrors(['inspection' => 'A noted pre-repair inspection can no longer be edited.']);
        }

        $data = $this->validatedInspection($request, true);
        $data = $this->withComputedTotals($data);
        $data['status'] = 'submitted';
        $data['work_request_id'] = $workRequest->id;
        $data['inspector_id'] = $data['inspector_id'] ?: ($workRequest->assigned_user_id ?: $request->user()->id);
        $data['inspected_at'] = now();
        $data['noted_by_id'] = null;
        $data['noted_at'] = null;
        $data['return_reason'] = null;

        WorkRequestPreRepairInspection::updateOrCreate(
            ['work_request_id' => $workRequest->id],
            $data,
        );

        return back()->with('success', 'Pre-repair inspection submitted.');
    }

    public function note(Request $request, WorkRequest $workRequest)
    {
        $this->authorizeManage($request);

        $inspection = $workRequest->preRepairInspection;
        if (! $inspection) {
            return back()->withErrors(['inspection' => 'Create and submit the pre-repair inspection first.']);
        }

        if ($inspection->status !== 'submitted') {
            return back()->withErrors(['inspection' => 'Only submitted pre-repair inspections can be noted.']);
        }

        $inspection->update([
            'status' => 'noted',
            'noted_by_id' => $request->user()->id,
            'noted_at' => now(),
            'return_reason' => null,
        ]);

        return back()->with('success', 'Pre-repair inspection noted.');
    }

    public function returnForRevision(Request $request, WorkRequest $workRequest)
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'return_reason' => 'required|string|max:1000',
        ]);

        $inspection = $workRequest->preRepairInspection;
        if (! $inspection) {
            return back()->withErrors(['inspection' => 'Create and submit the pre-repair inspection first.']);
        }

        $inspection->update([
            'status' => 'returned',
            'return_reason' => $data['return_reason'],
            'noted_by_id' => null,
            'noted_at' => null,
        ]);

        return back()->with('success', 'Pre-repair inspection returned for revision.');
    }

    public function print(Request $request, WorkRequest $workRequest)
    {
        $this->authorizeView($request, $workRequest);

        $workRequest->load([
            'division',
            'office',
            'requester',
            'assignedUser',
            'preRepairInspection.inspector',
            'preRepairInspection.notedBy',
        ]);

        return view('work_requests.pre_repair_inspection_print', [
            'workRequest' => $workRequest,
            'inspection' => $workRequest->preRepairInspection,
        ]);
    }

    private function validatedInspection(Request $request, bool $requireFindings): array
    {
        return $request->validate([
            'inspector_id' => 'nullable|exists:users,id',
            'asset_type' => 'nullable|string|in:building_facility,equipment_machinery',
            'building_facility' => 'nullable|string|max:255',
            'property_no' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'serial_no' => 'nullable|string|max:255',
            'date_of_purchase' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'warranty_status' => 'nullable|string|in:with_warranty,without_warranty,unknown',
            'location_of_defect' => 'nullable|string',
            'nature_of_defect' => [$requireFindings ? 'required' : 'nullable', 'string'],
            'cause_of_defect' => 'nullable|string',
            'recommendations' => [$requireFindings ? 'required' : 'nullable', 'string'],
            'material_items' => 'nullable|array',
            'material_items.*.item_description' => 'nullable|string|max:255',
            'material_items.*.qty' => 'nullable|numeric|min:0',
            'material_items.*.unit_cost' => 'nullable|numeric|min:0',
            'labor_items' => 'nullable|array',
            'labor_items.*.work_description' => 'nullable|string|max:255',
            'labor_items.*.man_days' => 'nullable|numeric|min:0',
            'labor_items.*.labor_rate' => 'nullable|numeric|min:0',
            'indirect_cost' => 'nullable|numeric|min:0',
        ]);
    }

    private function withComputedTotals(array $data): array
    {
        $data['material_items'] = collect($data['material_items'] ?? [])
            ->filter(fn ($item) => filled($item['item_description'] ?? null))
            ->map(function ($item) {
                $qty = (float) ($item['qty'] ?? 0);
                $unitCost = (float) ($item['unit_cost'] ?? 0);

                return [
                    'item_description' => $item['item_description'] ?? '',
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'total' => round($qty * $unitCost, 2),
                ];
            })
            ->values()
            ->all();

        $data['labor_items'] = collect($data['labor_items'] ?? [])
            ->filter(fn ($item) => filled($item['work_description'] ?? null))
            ->map(function ($item) {
                $manDays = (float) ($item['man_days'] ?? 0);
                $laborRate = (float) ($item['labor_rate'] ?? 0);

                return [
                    'work_description' => $item['work_description'] ?? '',
                    'man_days' => $manDays,
                    'labor_rate' => $laborRate,
                    'total' => round($manDays * $laborRate, 2),
                ];
            })
            ->values()
            ->all();

        $data['total_material_cost'] = collect($data['material_items'])->sum('total');
        $data['total_labor_cost'] = collect($data['labor_items'])->sum('total');
        $data['indirect_cost'] = (float) ($data['indirect_cost'] ?? 0);
        $data['total_estimated_repair_cost'] = round(
            $data['total_material_cost'] + $data['total_labor_cost'] + $data['indirect_cost'],
            2,
        );

        return $data;
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! ($user->hasRole('Administrator') || $user->hasRole('GSU Head') || $user->hasPermission('facilities.manage'))) {
            abort(403);
        }
    }

    private function authorizeEdit(Request $request, WorkRequest $workRequest): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if ($user->hasRole('Administrator') || $user->hasRole('GSU Head') || $user->hasPermission('facilities.manage')) {
            return;
        }

        $inspection = $workRequest->preRepairInspection;
        if (
            (int) $workRequest->assigned_user_id === (int) $user->id
            || ($inspection && (int) $inspection->inspector_id === (int) $user->id)
        ) {
            return;
        }

        abort(403);
    }

    private function authorizeView(Request $request, WorkRequest $workRequest): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if (
            $user->hasRole('Administrator')
            || $user->hasRole('GSU Head')
            || $user->hasRole('FAD Chief')
            || str_contains($user->position ?? '', 'FAD')
            || $user->hasPermission('facilities.manage')
            || (int) $workRequest->requester_id === (int) $user->id
            || (int) $workRequest->assigned_user_id === (int) $user->id
            || (int) optional($workRequest->preRepairInspection)->inspector_id === (int) $user->id
        ) {
            return;
        }

        abort(403);
    }
}
