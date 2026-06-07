<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Property\IcsItem;
use App\Models\Property\InventoryCustodianSlip;
use App\Models\Property\PropertyItem;
use App\Models\User;
use App\Services\Property\IcsPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class IcsController extends Controller
{
    public function __construct(private IcsPdfService $pdf) {}

    public function index()
    {
        $this->authorize('property.view');

        $ics = InventoryCustodianSlip::with([
            'receiver:id,name',
            'issuer:id,name',
            'division:id,division_name',
        ])->latest()->get()->map(fn ($i) => $this->formatIcs($i));

        return Inertia::render('Property/ICS/Index', ['ics_list' => $ics]);
    }

    public function create(Request $request)
    {
        $this->authorize('property.manage');

        return Inertia::render('Property/ICS/Create', [
            'propertyItems' => PropertyItem::where('status', 'serviceable')
                ->with('category:id,name,type')
                ->orderBy('description')
                ->get(['id', 'property_number', 'description', 'unit', 'unit_cost', 'category_id']),
            'officers'  => User::where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name']),
            'divisions' => Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('property.manage');

        $data = $request->validate([
            'issue_date'   => 'required|date',
            'issued_by'    => 'nullable|exists:users,id',
            'received_by'  => 'required|exists:users,id',
            'division_id'  => 'nullable|exists:divisions,id',
            'remarks'      => 'nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.property_item_id'      => 'nullable|exists:property_items,id',
            'items.*.description'           => 'required|string|max:255',
            'items.*.unit'                  => 'required|string|max:50',
            'items.*.quantity'              => 'required|numeric|min:0.001',
            'items.*.unit_cost'             => 'required|numeric|min:0',
            'items.*.estimated_useful_life' => 'nullable|date',
            'items.*.remarks'               => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $request) {
            $ics = InventoryCustodianSlip::create([
                'ics_number'  => InventoryCustodianSlip::generateNumber(),
                'issue_date'  => $data['issue_date'],
                'issued_by'   => $data['issued_by'] ?? $request->user()->id,
                'received_by' => $data['received_by'],
                'division_id' => $data['division_id'] ?? null,
                'status'      => 'active',
                'remarks'     => $data['remarks'] ?? null,
                'created_by'  => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                IcsItem::create([
                    'ics_id'                => $ics->id,
                    'property_item_id'      => $item['property_item_id'] ?? null,
                    'description'           => $item['description'],
                    'unit'                  => $item['unit'],
                    'quantity'              => $item['quantity'],
                    'unit_cost'             => $item['unit_cost'],
                    'estimated_useful_life' => $item['estimated_useful_life'] ?? null,
                    'remarks'               => $item['remarks'] ?? null,
                ]);

                // Link property item to the receiving officer
                if (! empty($item['property_item_id'])) {
                    PropertyItem::where('id', $item['property_item_id'])
                        ->update(['current_officer_id' => $data['received_by']]);
                }
            }
        });

        return redirect()->route('property.ics.index')->with('success', 'ICS created successfully.');
    }

    public function show(InventoryCustodianSlip $ics)
    {
        $this->authorize('property.view');

        $ics->load(['issuer:id,name', 'receiver:id,name', 'division:id,division_name',
            'creator:id,name', 'items.propertyItem:id,property_number,description,unit']);

        return Inertia::render('Property/ICS/Show', ['ics' => $this->formatIcsDetail($ics)]);
    }

    public function printPdf(InventoryCustodianSlip $ics)
    {
        $this->authorize('property.view');
        $ics->load(['issuer:id,name', 'receiver:id,name', 'division:id,division_name', 'items.propertyItem']);
        return $this->pdf->generate($ics);
    }

    private function formatIcs(InventoryCustodianSlip $ics): array
    {
        return [
            'id'            => $ics->id,
            'ics_number'    => $ics->ics_number,
            'issue_date'    => $ics->issue_date?->format('Y-m-d'),
            'issued_by'     => $ics->issuer?->name,
            'received_by'   => $ics->receiver?->name,
            'division_name' => $ics->division?->division_name,
            'status'        => $ics->status,
            'status_label'  => $ics->statusLabel(),
        ];
    }

    private function formatIcsDetail(InventoryCustodianSlip $ics): array
    {
        return [
            ...$this->formatIcs($ics),
            'issued_by_id'   => $ics->issued_by,
            'received_by_id' => $ics->received_by,
            'division_id'    => $ics->division_id,
            'remarks'        => $ics->remarks,
            'total_amount'   => $ics->totalAmount(),
            'items'          => $ics->items->map(fn ($item) => [
                'id'                    => $item->id,
                'property_number'       => $item->propertyItem?->property_number,
                'description'           => $item->description,
                'unit'                  => $item->unit,
                'quantity'              => (float) $item->quantity,
                'unit_cost'             => (float) $item->unit_cost,
                'total_cost'            => $item->totalCost(),
                'estimated_useful_life' => $item->estimated_useful_life?->format('Y-m-d'),
                'remarks'               => $item->remarks,
            ])->values(),
        ];
    }
}
