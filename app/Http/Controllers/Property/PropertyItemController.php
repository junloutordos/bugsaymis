<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Property\PropertyCategory;
use App\Models\Property\PropertyItem;
use App\Models\Supply\IarItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PropertyItemController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('property.view');

        $items = PropertyItem::with(['category:id,name,type,account_code', 'currentOfficer:id,name'])
            ->orderByDesc('acquisition_date')
            ->get()
            ->map(fn ($i) => $this->formatItem($i));

        $categories = PropertyCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'account_code', 'useful_life_years']);

        $officers = User::where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Property/Items/Index', [
            'items'      => $items,
            'categories' => $categories,
            'officers'   => $officers,
            'summary'    => [
                'total'         => $items->count(),
                'serviceable'   => $items->where('status', 'serviceable')->count(),
                'unserviceable' => $items->where('status', 'unserviceable')->count(),
                'total_cost'    => $items->sum('total_cost'),
                'book_value'    => $items->sum('book_value'),
            ],
        ]);
    }

    public function show(PropertyItem $item)
    {
        $this->authorize('property.view');

        $item->load(['category', 'currentOfficer:id,name', 'creator:id,name',
            'parItems.par', 'icsItems.ics']);

        return Inertia::render('Property/Items/Show', [
            'item' => $this->formatItemDetail($item),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('property.manage');

        $data = $request->validate([
            'description'      => 'required|string|max:255',
            'category_id'      => 'required|exists:property_categories,id',
            'unit'             => 'required|string|max:50',
            'quantity'         => 'required|numeric|min:0.001',
            'unit_cost'        => 'required|numeric|min:0',
            'acquisition_date' => 'required|date',
            'acquisition_mode' => 'required|in:purchase,donation,transfer,fabricated',
            'supplier_name'    => 'nullable|string|max:255',
            'brand'            => 'nullable|string|max:100',
            'model'            => 'nullable|string|max:100',
            'serial_number'    => 'nullable|string|max:100',
            'location'         => 'nullable|string|max:255',
            'current_officer_id' => 'nullable|exists:users,id',
            'remarks'          => 'nullable|string',
        ]);

        $data['property_number'] = PropertyItem::generateNumber('PROP');
        $data['status']          = 'serviceable';
        $data['created_by']      = $request->user()->id;

        PropertyItem::create($data);

        return back()->with('success', 'Property item recorded.');
    }

    public function update(Request $request, PropertyItem $item)
    {
        $this->authorize('property.manage');

        $data = $request->validate([
            'description'        => 'required|string|max:255',
            'category_id'        => 'required|exists:property_categories,id',
            'unit'               => 'required|string|max:50',
            'unit_cost'          => 'required|numeric|min:0',
            'acquisition_date'   => 'required|date',
            'acquisition_mode'   => 'required|in:purchase,donation,transfer,fabricated',
            'supplier_name'      => 'nullable|string|max:255',
            'brand'              => 'nullable|string|max:100',
            'model'              => 'nullable|string|max:100',
            'serial_number'      => 'nullable|string|max:100',
            'location'           => 'nullable|string|max:255',
            'current_officer_id' => 'nullable|exists:users,id',
            'status'             => 'required|in:serviceable,unserviceable,disposed,transferred,lost',
            'remarks'            => 'nullable|string',
        ]);

        $item->update($data);

        return back()->with('success', 'Property item updated.');
    }

    public function storeCategory(Request $request)
    {
        $this->authorize('property.manage');

        $data = $request->validate([
            'name'              => 'required|string|max:100|unique:property_categories,name',
            'account_code'      => 'nullable|string|max:20',
            'type'              => 'required|in:semi_expendable,equipment',
            'useful_life_years' => 'required|integer|min:1|max:50',
            'residual_rate'     => 'required|numeric|min:0|max:1',
            'description'       => 'nullable|string',
        ]);

        $data['is_active'] = true;
        PropertyCategory::create($data);

        return back()->with('success', 'Category created.');
    }

    private function formatItem(PropertyItem $item): array
    {
        return [
            'id'               => $item->id,
            'property_number'  => $item->property_number,
            'description'      => $item->description,
            'category_id'      => $item->category_id,
            'category_name'    => $item->category?->name,
            'category_type'    => $item->category?->type,
            'account_code'     => $item->category?->account_code,
            'unit'             => $item->unit,
            'quantity'         => (float) $item->quantity,
            'unit_cost'        => (float) $item->unit_cost,
            'total_cost'       => $item->totalCost(),
            'acquisition_date' => $item->acquisition_date?->format('Y-m-d'),
            'acquisition_mode' => $item->acquisition_mode,
            'brand'            => $item->brand,
            'model'            => $item->model,
            'serial_number'    => $item->serial_number,
            'location'         => $item->location,
            'current_officer'  => $item->currentOfficer?->name,
            'current_officer_id' => $item->current_officer_id,
            'status'           => $item->status,
            'book_value'       => $item->bookValue(),
            'accumulated_dep'  => $item->accumulatedDepreciation(),
            'monthly_dep'      => $item->monthlyDepreciation(),
        ];
    }

    private function formatItemDetail(PropertyItem $item): array
    {
        return [
            ...$this->formatItem($item),
            'supplier_name'    => $item->supplier_name,
            'remarks'          => $item->remarks,
            'residual_value'   => $item->residualValue(),
            'depreciable_amount' => $item->depreciableAmount(),
            'useful_life_years' => $item->category?->useful_life_years,
            'par_items'        => $item->parItems->map(fn ($pi) => [
                'par_number'  => $pi->par?->par_number,
                'issue_date'  => $pi->par?->issue_date?->format('Y-m-d'),
            ])->values(),
            'ics_items'        => $item->icsItems->map(fn ($ii) => [
                'ics_number'  => $ii->ics?->ics_number,
                'issue_date'  => $ii->ics?->issue_date?->format('Y-m-d'),
            ])->values(),
        ];
    }
}
