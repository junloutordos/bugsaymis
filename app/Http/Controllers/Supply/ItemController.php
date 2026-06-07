<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Models\Supply\SupplyCategory;
use App\Models\Supply\SupplyItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('supply.manage');

        $items = SupplyItem::with(['category', 'stockCard'])
            ->orderBy('description')
            ->get()
            ->map(fn ($i) => $this->formatItem($i));

        $categories = SupplyCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'account_code']);

        return Inertia::render('Supply/Items/Index', [
            'items'      => $items,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('supply.manage');

        $data = $request->validate([
            'description'         => 'required|string|max:255',
            'unit'                => 'required|string|max:50',
            'category_id'         => 'required|exists:supply_categories,id',
            'estimated_unit_cost' => 'required|numeric|min:0',
            'reorder_point'       => 'nullable|integer|min:0',
            'reorder_quantity'    => 'nullable|integer|min:0',
            'specifications'      => 'nullable|string',
        ]);

        $data['item_code'] = SupplyItem::generateCode($data['category_id']);
        $data['is_active']  = true;

        SupplyItem::create($data);

        return back()->with('success', 'Supply item created.');
    }

    public function update(Request $request, SupplyItem $item)
    {
        $this->authorize('supply.manage');

        $data = $request->validate([
            'description'         => 'required|string|max:255',
            'unit'                => 'required|string|max:50',
            'category_id'         => 'required|exists:supply_categories,id',
            'estimated_unit_cost' => 'required|numeric|min:0',
            'reorder_point'       => 'nullable|integer|min:0',
            'reorder_quantity'    => 'nullable|integer|min:0',
            'specifications'      => 'nullable|string',
        ]);

        $item->update($data);

        return back()->with('success', 'Supply item updated.');
    }

    public function toggleActive(SupplyItem $item)
    {
        $this->authorize('supply.manage');

        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('success', $item->is_active ? 'Item activated.' : 'Item deactivated.');
    }

    // Categories sub-resource

    public function storeCategory(Request $request)
    {
        $this->authorize('supply.manage');

        $data = $request->validate([
            'name'         => 'required|string|max:100|unique:supply_categories,name',
            'account_code' => 'nullable|string|max:20',
            'type'         => 'required|in:consumable,semi_expendable,equipment',
            'description'  => 'nullable|string',
        ]);

        $data['is_active'] = true;

        SupplyCategory::create($data);

        return back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, SupplyCategory $category)
    {
        $this->authorize('supply.manage');

        $data = $request->validate([
            'name'         => "required|string|max:100|unique:supply_categories,name,{$category->id}",
            'account_code' => 'nullable|string|max:20',
            'type'         => 'required|in:consumable,semi_expendable,equipment',
            'description'  => 'nullable|string',
        ]);

        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    private function formatItem(SupplyItem $item): array
    {
        return [
            'id'                   => $item->id,
            'item_code'            => $item->item_code,
            'description'          => $item->description,
            'unit'                 => $item->unit,
            'category_id'          => $item->category_id,
            'category_name'        => $item->category?->name,
            'category_type'        => $item->category?->type,
            'account_code'         => $item->category?->account_code,
            'estimated_unit_cost'  => (float) $item->estimated_unit_cost,
            'reorder_point'        => $item->reorder_point,
            'reorder_quantity'     => $item->reorder_quantity,
            'specifications'       => $item->specifications,
            'is_active'            => $item->is_active,
            'balance_quantity'     => (float) ($item->stockCard?->balance_quantity ?? 0),
            'average_unit_cost'    => (float) ($item->stockCard?->average_unit_cost ?? $item->estimated_unit_cost),
            'is_low_stock'         => $item->isLowStock(),
            'total_value'          => round(
                (float) ($item->stockCard?->balance_quantity ?? 0) *
                (float) ($item->stockCard?->average_unit_cost ?? $item->estimated_unit_cost),
                2
            ),
        ];
    }
}
