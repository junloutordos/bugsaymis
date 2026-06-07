<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Property\PropertyItem;
use App\Models\Property\WorkOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkOrderController extends Controller
{
    public function index()
    {
        $this->authorize('work-orders.view');

        $workOrders = WorkOrder::with([
            'propertyItem:id,property_number,description',
            'requester:id,name',
            'assignee:id,name',
        ])->latest()->get()->map(fn ($wo) => $this->formatWo($wo));

        return Inertia::render('Property/WorkOrders/Index', [
            'workOrders'    => $workOrders,
            'propertyItems' => PropertyItem::where('status', 'serviceable')
                ->orderBy('property_number')
                ->get(['id', 'property_number', 'description']),
            'officers' => User::where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('work-orders.view');

        $data = $request->validate([
            'property_item_id' => 'nullable|exists:property_items,id',
            'description'      => 'required|string|max:500',
            'priority'         => 'required|in:low,normal,high,urgent',
            'estimated_cost'   => 'nullable|numeric|min:0',
            'requested_date'   => 'required|date',
            'target_date'      => 'nullable|date|after_or_equal:requested_date',
            'remarks'          => 'nullable|string',
        ]);

        WorkOrder::create([
            ...$data,
            'work_order_number' => WorkOrder::generateNumber(),
            'requested_by'      => $request->user()->id,
            'status'            => 'pending',
            'created_by'        => $request->user()->id,
        ]);

        return back()->with('success', 'Work order created.');
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        $this->authorize('work-orders.manage');

        $data = $request->validate([
            'assigned_to'    => 'nullable|exists:users,id',
            'status'         => 'required|in:pending,in_progress,completed,cancelled',
            'actual_cost'    => 'nullable|numeric|min:0',
            'completed_date' => 'nullable|date',
            'remarks'        => 'nullable|string',
        ]);

        $workOrder->update($data);

        return back()->with('success', 'Work order updated.');
    }

    private function formatWo(WorkOrder $wo): array
    {
        return [
            'id'                => $wo->id,
            'work_order_number' => $wo->work_order_number,
            'property_number'   => $wo->propertyItem?->property_number,
            'description'       => $wo->description,
            'requester'         => $wo->requester?->name,
            'assignee'          => $wo->assignee?->name,
            'priority'          => $wo->priority,
            'priority_label'    => $wo->priorityLabel(),
            'status'            => $wo->status,
            'status_label'      => $wo->statusLabel(),
            'estimated_cost'    => (float) ($wo->estimated_cost ?? 0),
            'actual_cost'       => (float) ($wo->actual_cost ?? 0),
            'requested_date'    => $wo->requested_date?->format('Y-m-d'),
            'target_date'       => $wo->target_date?->format('Y-m-d'),
            'completed_date'    => $wo->completed_date?->format('Y-m-d'),
            'remarks'           => $wo->remarks,
        ];
    }
}
