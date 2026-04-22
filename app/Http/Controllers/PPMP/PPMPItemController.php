<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpItem;
use Illuminate\Http\Request;

class PPMPItemController extends Controller
{
    /**
     * Add a line item to a PPMP.
     */
    public function store(Request $request, Ppmp $ppmp)
    {
        $this->authorize('update', $ppmp);

        $data = $request->validate([
            'code'               => 'nullable|string|max:50',
            'description'        => 'required|string|max:500',
            'unit'               => 'required|string|max:50',
            'category'           => 'required|in:' . implode(',', PpmpItem::CATEGORIES),
            'unit_cost'          => 'required|numeric|min:0.01',
            'jan'                => 'nullable|integer|min:0',
            'feb'                => 'nullable|integer|min:0',
            'mar'                => 'nullable|integer|min:0',
            'apr'                => 'nullable|integer|min:0',
            'may'                => 'nullable|integer|min:0',
            'jun'                => 'nullable|integer|min:0',
            'jul'                => 'nullable|integer|min:0',
            'aug'                => 'nullable|integer|min:0',
            'sep'                => 'nullable|integer|min:0',
            'oct'                => 'nullable|integer|min:0',
            'nov'                => 'nullable|integer|min:0',
            'dec'                => 'nullable|integer|min:0',
            'procurement_method' => 'required|in:' . implode(',', array_keys(PpmpItem::PROCUREMENT_METHODS)),
            'is_ps_dbm'          => 'boolean',
            'remarks'            => 'nullable|string|max:1000',
        ]);

        $data['ppmp_id'] = $ppmp->id;
        $data['sort_order'] = $ppmp->items()->where('category', $data['category'])->count();

        // Default month fields to 0 if not provided
        foreach (PpmpItem::MONTHS as $m) {
            $data[$m] = $data[$m] ?? 0;
        }

        PpmpItem::create($data);

        return back()->with('success', 'Item added.');
    }

    /**
     * Update a line item.
     */
    public function update(Request $request, Ppmp $ppmp, PpmpItem $item)
    {
        $this->authorize('update', $ppmp);

        abort_if($item->ppmp_id !== $ppmp->id, 404);

        $data = $request->validate([
            'code'               => 'nullable|string|max:50',
            'description'        => 'required|string|max:500',
            'unit'               => 'required|string|max:50',
            'category'           => 'required|in:' . implode(',', PpmpItem::CATEGORIES),
            'unit_cost'          => 'required|numeric|min:0.01',
            'jan'                => 'nullable|integer|min:0',
            'feb'                => 'nullable|integer|min:0',
            'mar'                => 'nullable|integer|min:0',
            'apr'                => 'nullable|integer|min:0',
            'may'                => 'nullable|integer|min:0',
            'jun'                => 'nullable|integer|min:0',
            'jul'                => 'nullable|integer|min:0',
            'aug'                => 'nullable|integer|min:0',
            'sep'                => 'nullable|integer|min:0',
            'oct'                => 'nullable|integer|min:0',
            'nov'                => 'nullable|integer|min:0',
            'dec'                => 'nullable|integer|min:0',
            'procurement_method' => 'required|in:' . implode(',', array_keys(PpmpItem::PROCUREMENT_METHODS)),
            'is_ps_dbm'          => 'boolean',
            'remarks'            => 'nullable|string|max:1000',
        ]);

        // Default month fields to 0 if not provided
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
