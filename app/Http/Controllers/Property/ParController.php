<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Property\ParItem;
use App\Models\Property\PropertyAcknowledgmentReceipt;
use App\Models\Property\PropertyItem;
use App\Models\User;
use App\Services\Property\ParPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ParController extends Controller
{
    public function __construct(private ParPdfService $pdf) {}

    public function index()
    {
        $this->authorize('property.view');

        $pars = PropertyAcknowledgmentReceipt::with([
            'receiver:id,name',
            'issuer:id,name',
            'division:id,division_name',
        ])->latest()->get()->map(fn ($p) => $this->formatPar($p));

        return Inertia::render('Property/PAR/Index', ['pars' => $pars]);
    }

    public function create()
    {
        $this->authorize('property.manage');

        return Inertia::render('Property/PAR/Create', [
            'propertyItems' => PropertyItem::where('status', 'serviceable')
                ->with('category:id,name,type')
                ->orderBy('description')
                ->get(['id', 'property_number', 'description', 'unit', 'unit_cost', 'quantity', 'category_id']),
            'officers'  => User::employees()->where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name']),
            'divisions' => Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('property.manage');

        $data = $request->validate([
            'issue_date'  => 'required|date',
            'issued_by'   => 'nullable|exists:users,id',
            'received_by' => 'required|exists:users,id',
            'division_id' => 'nullable|exists:divisions,id',
            'remarks'     => 'nullable|string',
            'items'       => 'required|array|min:1',
            'items.*.property_item_id' => 'required|exists:property_items,id',
            'items.*.remarks'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $request) {
            $par = PropertyAcknowledgmentReceipt::create([
                'par_number'  => PropertyAcknowledgmentReceipt::generateNumber(),
                'issue_date'  => $data['issue_date'],
                'issued_by'   => $data['issued_by'] ?? $request->user()->id,
                'received_by' => $data['received_by'],
                'division_id' => $data['division_id'] ?? null,
                'status'      => 'active',
                'remarks'     => $data['remarks'] ?? null,
                'created_by'  => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                $prop = PropertyItem::findOrFail($item['property_item_id']);

                ParItem::create([
                    'par_id'           => $par->id,
                    'property_item_id' => $prop->id,
                    'description'      => $prop->description,
                    'unit'             => $prop->unit,
                    'quantity'         => $prop->quantity,
                    'unit_cost'        => $prop->unit_cost,
                    'remarks'          => $item['remarks'] ?? null,
                ]);

                $prop->update(['current_officer_id' => $data['received_by']]);
            }
        });

        return redirect()->route('property.par.index')->with('success', 'PAR created successfully.');
    }

    public function show(PropertyAcknowledgmentReceipt $par)
    {
        $this->authorize('property.view');
        $par->load(['issuer:id,name', 'receiver:id,name', 'division:id,division_name',
            'creator:id,name', 'items.propertyItem:id,property_number,description']);
        return Inertia::render('Property/PAR/Show', ['par' => $this->formatParDetail($par)]);
    }

    public function printPdf(PropertyAcknowledgmentReceipt $par)
    {
        $this->authorize('property.view');
        $par->load(['issuer:id,name', 'receiver:id,name', 'division:id,division_name', 'items.propertyItem']);
        return $this->pdf->generate($par);
    }

    private function formatPar(PropertyAcknowledgmentReceipt $par): array
    {
        return [
            'id'            => $par->id,
            'par_number'    => $par->par_number,
            'issue_date'    => $par->issue_date?->format('Y-m-d'),
            'issued_by'     => $par->issuer?->name,
            'received_by'   => $par->receiver?->name,
            'division_name' => $par->division?->division_name,
            'status'        => $par->status,
            'status_label'  => $par->statusLabel(),
        ];
    }

    private function formatParDetail(PropertyAcknowledgmentReceipt $par): array
    {
        return [
            ...$this->formatPar($par),
            'division_id'    => $par->division_id,
            'remarks'        => $par->remarks,
            'total_amount'   => $par->totalAmount(),
            'items'          => $par->items->map(fn ($item) => [
                'id'               => $item->id,
                'property_number'  => $item->propertyItem?->property_number,
                'description'      => $item->description,
                'unit'             => $item->unit,
                'quantity'         => (float) $item->quantity,
                'unit_cost'        => (float) $item->unit_cost,
                'total_cost'       => $item->totalCost(),
                'remarks'          => $item->remarks,
            ])->values(),
        ];
    }
}
