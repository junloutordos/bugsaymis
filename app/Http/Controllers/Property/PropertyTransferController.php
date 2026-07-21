<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Property\PropertyItem;
use App\Models\Property\PropertyTransfer;
use App\Models\Property\PropertyTransferItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PropertyTransferController extends Controller
{
    public function index()
    {
        $this->authorize('property.transfer');

        $transfers = PropertyTransfer::with([
            'fromOfficer:id,name',
            'toOfficer:id,name',
        ])->latest()->get()->map(fn ($t) => [
            'id'              => $t->id,
            'transfer_number' => $t->transfer_number,
            'from_officer'    => $t->fromOfficer?->name,
            'to_officer'      => $t->toOfficer?->name,
            'transfer_date'   => $t->transfer_date?->format('Y-m-d'),
            'status'          => $t->status,
            'status_label'    => $t->statusLabel(),
            'item_count'      => $t->items()->count(),
        ]);

        return Inertia::render('Property/Transfers/Index', [
            'transfers' => $transfers,
            'officers'  => User::employees()->where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name']),
            'propertyItems' => PropertyItem::where('status', 'serviceable')
                ->orderBy('property_number')
                ->get(['id', 'property_number', 'description', 'current_officer_id']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('property.transfer');

        $data = $request->validate([
            'from_officer_id'  => 'required|exists:users,id',
            'to_officer_id'    => 'required|exists:users,id|different:from_officer_id',
            'transfer_date'    => 'required|date',
            'reason'           => 'nullable|string|max:500',
            'remarks'          => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.property_item_id' => 'required|exists:property_items,id',
            'items.*.remarks'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $request) {
            $transfer = PropertyTransfer::create([
                'transfer_number'  => PropertyTransfer::generateNumber(),
                'from_officer_id'  => $data['from_officer_id'],
                'to_officer_id'    => $data['to_officer_id'],
                'transfer_date'    => $data['transfer_date'],
                'reason'           => $data['reason'] ?? null,
                'status'           => 'pending',
                'remarks'          => $data['remarks'] ?? null,
                'created_by'       => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                PropertyTransferItem::create([
                    'transfer_id'       => $transfer->id,
                    'property_item_id'  => $item['property_item_id'],
                    'remarks'           => $item['remarks'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Transfer request created.');
    }

    public function complete(Request $request, PropertyTransfer $transfer)
    {
        $this->authorize('property.transfer');
        abort_unless($transfer->status === 'pending', 422, 'Transfer is not in pending status.');

        DB::transaction(function () use ($transfer) {
            $transfer->load('items');

            foreach ($transfer->items as $transferItem) {
                PropertyItem::where('id', $transferItem->property_item_id)
                    ->update([
                        'current_officer_id' => $transfer->to_officer_id,
                        'status'             => 'serviceable',
                    ]);
            }

            $transfer->update(['status' => 'completed']);
        });

        return back()->with('success', 'Transfer completed. Property accountability updated.');
    }

    public function cancel(PropertyTransfer $transfer)
    {
        $this->authorize('property.transfer');
        abort_unless($transfer->status === 'pending', 422, 'Transfer cannot be cancelled at this stage.');

        $transfer->update(['status' => 'cancelled']);

        return back()->with('success', 'Transfer cancelled.');
    }
}
