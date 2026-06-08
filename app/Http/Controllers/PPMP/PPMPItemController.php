<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpCatalogue;
use App\Models\PPMP\PpmpItem;
use Illuminate\Http\Request;

class PPMPItemController extends Controller
{
    /**
     * Add a line item — two paths:
     *   Part I  (catalogue_id present): pull description/unit/cost from catalogue, lock method to agency_to_agency
     *   Part II (catalogue_id absent):  full manual entry
     */
    public function store(Request $request, Ppmp $ppmp)
    {
        $this->authorize('update', $ppmp);

        $catalogueId = $request->input('catalogue_id');

        if ($catalogueId) {
            return $this->storePartI($request, $ppmp, (int) $catalogueId);
        }

        return $this->storePartII($request, $ppmp);
    }

    private function storePartI(Request $request, Ppmp $ppmp, int $catalogueId): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'catalogue_id'        => 'required|integer|exists:ppmp_catalogue,id',
            'fund_source'         => 'required|in:' . implode(',', array_keys(PpmpItem::FUND_SOURCES)),
            'procurement_quarter' => 'nullable|integer|min:1|max:4',
            'delivery_quarter'    => 'nullable|integer|min:1|max:4',
            'remarks'             => 'nullable|string|max:1000',
            'jan'                 => 'nullable|integer|min:0',
            'feb'                 => 'nullable|integer|min:0',
            'mar'                 => 'nullable|integer|min:0',
            'apr'                 => 'nullable|integer|min:0',
            'may'                 => 'nullable|integer|min:0',
            'jun'                 => 'nullable|integer|min:0',
            'jul'                 => 'nullable|integer|min:0',
            'aug'                 => 'nullable|integer|min:0',
            'sep'                 => 'nullable|integer|min:0',
            'oct'                 => 'nullable|integer|min:0',
            'nov'                 => 'nullable|integer|min:0',
            'dec'                 => 'nullable|integer|min:0',
        ]);

        $catalogue = PpmpCatalogue::findOrFail($catalogueId);

        // description, unit, unit_cost and method come from catalogue — not user input
        $record = array_merge($data, [
            'ppmp_id'            => $ppmp->id,
            'code'               => $catalogue->stock_number,
            'description'        => $catalogue->description,
            'unit'               => $catalogue->unit,
            'unit_cost'          => $catalogue->unit_cost,
            'category'           => 'goods', // PS-DBM CUSE items are always Goods
            'procurement_method' => 'agency_to_agency',
            'is_ps_dbm'          => true,
            'price_source'       => 'PS-DBM Catalogue',
            'price_validity_date'=> $catalogue->price_validity_date,
            'sort_order'         => $ppmp->items()->where('catalogue_id', '!=', null)->count(),
        ]);

        foreach (PpmpItem::MONTHS as $m) {
            $record[$m] = $record[$m] ?? 0;
        }

        PpmpItem::create($record);

        return back()->with('success', 'PS-DBM item added (Part I).');
    }

    private function storePartII(Request $request, Ppmp $ppmp): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'code'                => 'nullable|string|max:50',
            'description'         => 'required|string|max:500',
            'unit'                => 'required|string|max:50',
            'category'            => 'required|in:' . implode(',', PpmpItem::CATEGORIES),
            'unit_cost'           => 'required|numeric|min:0.01',
            'jan'                 => 'nullable|integer|min:0',
            'feb'                 => 'nullable|integer|min:0',
            'mar'                 => 'nullable|integer|min:0',
            'apr'                 => 'nullable|integer|min:0',
            'may'                 => 'nullable|integer|min:0',
            'jun'                 => 'nullable|integer|min:0',
            'jul'                 => 'nullable|integer|min:0',
            'aug'                 => 'nullable|integer|min:0',
            'sep'                 => 'nullable|integer|min:0',
            'oct'                 => 'nullable|integer|min:0',
            'nov'                 => 'nullable|integer|min:0',
            'dec'                 => 'nullable|integer|min:0',
            'procurement_method'  => 'required|in:' . implode(',', array_keys(PpmpItem::PROCUREMENT_METHODS)),
            'remarks'             => 'nullable|string|max:1000',
            'fund_source'         => 'required|in:' . implode(',', array_keys(PpmpItem::FUND_SOURCES)),
            'procurement_quarter' => 'nullable|integer|min:1|max:4',
            'delivery_quarter'    => 'nullable|integer|min:1|max:4',
            'price_source'        => 'nullable|string|max:100',
            'price_validity_date' => 'nullable|date',
        ]);

        $data['ppmp_id']    = $ppmp->id;
        $data['is_ps_dbm']  = false;
        $data['catalogue_id'] = null;
        $data['sort_order'] = $ppmp->items()->where('category', $data['category'])->whereNull('catalogue_id')->count();

        foreach (PpmpItem::MONTHS as $m) {
            $data[$m] = $data[$m] ?? 0;
        }

        PpmpItem::create($data);

        return back()->with('success', 'Item added (Part II).');
    }

    /**
     * Update a line item.
     * Part I items: only monthly quantities, fund source, quarters, and remarks are editable.
     */
    public function update(Request $request, Ppmp $ppmp, PpmpItem $item)
    {
        $this->authorize('update', $ppmp);
        abort_if($item->ppmp_id !== $ppmp->id, 404);

        if ($item->catalogue_id) {
            // Part I — only schedule/fund fields editable
            $data = $request->validate([
                'fund_source'         => 'required|in:' . implode(',', array_keys(PpmpItem::FUND_SOURCES)),
                'procurement_quarter' => 'nullable|integer|min:1|max:4',
                'delivery_quarter'    => 'nullable|integer|min:1|max:4',
                'remarks'             => 'nullable|string|max:1000',
                'jan'                 => 'nullable|integer|min:0',
                'feb'                 => 'nullable|integer|min:0',
                'mar'                 => 'nullable|integer|min:0',
                'apr'                 => 'nullable|integer|min:0',
                'may'                 => 'nullable|integer|min:0',
                'jun'                 => 'nullable|integer|min:0',
                'jul'                 => 'nullable|integer|min:0',
                'aug'                 => 'nullable|integer|min:0',
                'sep'                 => 'nullable|integer|min:0',
                'oct'                 => 'nullable|integer|min:0',
                'nov'                 => 'nullable|integer|min:0',
                'dec'                 => 'nullable|integer|min:0',
            ]);

            foreach (PpmpItem::MONTHS as $m) {
                $data[$m] = $data[$m] ?? 0;
            }

            $item->update($data);

            return back()->with('success', 'PS-DBM item schedule updated.');
        }

        // Part II — full update
        $data = $request->validate([
            'code'                => 'nullable|string|max:50',
            'description'         => 'required|string|max:500',
            'unit'                => 'required|string|max:50',
            'category'            => 'required|in:' . implode(',', PpmpItem::CATEGORIES),
            'unit_cost'           => 'required|numeric|min:0.01',
            'jan'                 => 'nullable|integer|min:0',
            'feb'                 => 'nullable|integer|min:0',
            'mar'                 => 'nullable|integer|min:0',
            'apr'                 => 'nullable|integer|min:0',
            'may'                 => 'nullable|integer|min:0',
            'jun'                 => 'nullable|integer|min:0',
            'jul'                 => 'nullable|integer|min:0',
            'aug'                 => 'nullable|integer|min:0',
            'sep'                 => 'nullable|integer|min:0',
            'oct'                 => 'nullable|integer|min:0',
            'nov'                 => 'nullable|integer|min:0',
            'dec'                 => 'nullable|integer|min:0',
            'procurement_method'  => 'required|in:' . implode(',', array_keys(PpmpItem::PROCUREMENT_METHODS)),
            'remarks'             => 'nullable|string|max:1000',
            'fund_source'         => 'required|in:' . implode(',', array_keys(PpmpItem::FUND_SOURCES)),
            'procurement_quarter' => 'nullable|integer|min:1|max:4',
            'delivery_quarter'    => 'nullable|integer|min:1|max:4',
            'price_source'        => 'nullable|string|max:100',
            'price_validity_date' => 'nullable|date',
        ]);

        foreach (PpmpItem::MONTHS as $m) {
            $data[$m] = $data[$m] ?? 0;
        }

        $item->update($data);

        return back()->with('success', 'Item updated.');
    }

    /**
     * Remove a line item.
     */
    public function destroy(Ppmp $ppmp, PpmpItem $item)
    {
        $this->authorize('update', $ppmp);
        abort_if($item->ppmp_id !== $ppmp->id, 404);

        $item->delete();

        return back()->with('success', 'Item removed.');
    }
}
