<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\ScienceLab\LabConsumable;
use App\Models\ScienceLab\LabEquipment;
use App\Services\ScienceLab\LabInventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LabReportController extends Controller
{
    public function __construct(private LabInventoryService $inventory) {}

    /**
     * Inventory Report (CIM 4.4 §5.2.1) — annual equipment + consumables balances.
     */
    public function inventory(Request $request)
    {
        $equipment = LabEquipment::with('room:id,name')->orderBy('unit')->orderBy('description')->get()
            ->map(fn ($e) => [
                'property_no'   => $e->property_no,
                'description'   => $e->description,
                'model'         => $e->model,
                'serial_number' => $e->serial_number,
                'location'      => $e->room?->name,
                'unit'          => $e->unit,
                'date_acquired' => optional($e->date_acquired)->toDateString(),
                'unit_cost'     => $e->unit_cost,
                'status'        => $e->status,
            ]);

        $consumables = LabConsumable::orderBy('type')->orderBy('name')->get()
            ->map(fn ($c) => [
                'name'          => $c->name,
                'type'          => $c->type,
                'unit'          => $c->unit_of_measure,
                'balance'       => $this->inventory->balance($c->id),
                'reorder_level' => (float) $c->reorder_level,
                'is_low'        => $c->reorder_level > 0 && $this->inventory->balance($c->id) <= (float) $c->reorder_level,
            ]);

        return Inertia::render('ScienceLab/ReportInventory', [
            'equipment'    => $equipment,
            'consumables'  => $consumables,
            'expiringSoon' => $this->inventory->expiringSoon(60)->map(fn ($l) => [
                'name' => $l->consumable?->name, 'lot_no' => $l->lot_no,
                'quantity' => (float) $l->quantity, 'expiry_date' => optional($l->expiry_date)->toDateString(),
            ]),
            'generatedAt'  => now()->toDateTimeString(),
        ]);
    }
}
