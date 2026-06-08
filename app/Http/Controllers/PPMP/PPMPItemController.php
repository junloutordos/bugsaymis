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
     * Add a line item — three paths:
     *   Part I catalogue  (catalogue.part === 1): pull all fields from catalogue, lock to agency_to_agency
     *   Part II catalogue (catalogue.part === 2): pull description/unit from catalogue, require office_unit_cost
     *   Manual            (no catalogue_id):      full free-form entry
     */
    public function store(Request $request, Ppmp $ppmp)
    {
        $this->authorize('update', $ppmp);

        $catalogueId = $request->input('catalogue_id');

        if ($catalogueId) {
            $catalogue = PpmpCatalogue::findOrFail((int) $catalogueId);
            if ($catalogue->part === 1) {
                return $this->storePartICatalogue($request, $ppmp, $catalogue);
            }
            return $this->storePartIICatalogue($request, $ppmp, $catalogue);
        }

        return $this->storeManual($request, $ppmp);
    }

    private function monthValidation(): array
    {
        return collect(PpmpItem::MONTHS)->mapWithKeys(fn ($m) => [$m => 'nullable|integer|min:0'])->all();
    }

    private function storePartICatalogue(Request $request, Ppmp $ppmp, PpmpCatalogue $catalogue): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(array_merge([
            'catalogue_id'        => 'required|integer|exists:ppmp_catalogue,id',
            'fund_source'         => 'required|in:' . implode(',', array_keys(PpmpItem::FUND_SOURCES)),
            'procurement_quarter' => 'nullable|integer|min:1|max:4',
            'delivery_quarter'    => 'nullable|integer|min:1|max:4',
            'remarks'             => 'nullable|string|max:1000',
        ], $this->monthValidation()));

        $record = array_merge($data, [
            'ppmp_id'             => $ppmp->id,
            'catalogue_id'        => $catalogue->id,
            'code'                => $catalogue->stock_number,
            'description'         => $catalogue->description,
            'unit'                => $catalogue->unit,
            'unit_cost'           => $catalogue->unit_cost,
            'category'            => 'goods',
            'procurement_method'  => 'agency_to_agency',
            'is_ps_dbm'           => true,
            'price_source'        => 'PS-DBM Catalogue',
            'price_validity_date' => $catalogue->price_validity_date,
            'sort_order'          => $ppmp->items()->whereNotNull('catalogue_id')->count(),
        ]);

        foreach (PpmpItem::MONTHS as $m) {
            $record[$m] = $record[$m] ?? 0;
        }

        PpmpItem::create($record);

        return back()->with('success', 'PS-DBM item added (Part I).');
    }

    private function storePartIICatalogue(Request $request, Ppmp $ppmp, PpmpCatalogue $catalogue): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(array_merge([
            'catalogue_id'        => 'required|integer|exists:ppmp_catalogue,id',
            'office_unit_cost'    => 'required|numeric|min:0.01',
            'fund_source'         => 'required|in:' . implode(',', array_keys(PpmpItem::FUND_SOURCES)),
            'procurement_method'  => 'required|in:' . implode(',', array_keys(PpmpItem::PROCUREMENT_METHODS)),
            'procurement_quarter' => 'nullable|integer|min:1|max:4',
            'delivery_quarter'    => 'nullable|integer|min:1|max:4',
            'remarks'             => 'nullable|string|max:1000',
            'price_source'        => 'nullable|string|max:100',
            'price_validity_date' => 'nullable|date',
        ], $this->monthValidation()));

        $record = array_merge($data, [
            'ppmp_id'     => $ppmp->id,
            'catalogue_id'=> $catalogue->id,
            'code'        => $catalogue->stock_number,
            'description' => $catalogue->description,
            'unit'        => $catalogue->unit,
            'unit_cost'   => $catalogue->unit_cost, // reference only; effective cost = office_unit_cost
            'category'    => 'goods',
            'is_ps_dbm'   => true,
            'sort_order'  => $ppmp->items()->whereNull('catalogue_id')->count(),
        ]);

        foreach (PpmpItem::MONTHS as $m) {
            $record[$m] = $record[$m] ?? 0;
        }

        PpmpItem::create($record);

        return back()->with('success', 'Part II catalogue item added.');
    }

    private function storeManual(Request $request, Ppmp $ppmp): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(array_merge([
            'code'                => 'nullable|string|max:50',
            'description'         => 'required|string|max:500',
            'unit'                => 'required|string|max:50',
            'category'            => 'required|in:' . implode(',', PpmpItem::CATEGORIES),
            'unit_cost'           => 'required|numeric|min:0.01',
            'procurement_method'  => 'required|in:' . implode(',', array_keys(PpmpItem::PROCUREMENT_METHODS)),
            'remarks'             => 'nullable|string|max:1000',
            'fund_source'         => 'required|in:' . implode(',', array_keys(PpmpItem::FUND_SOURCES)),
            'procurement_quarter' => 'nullable|integer|min:1|max:4',
            'delivery_quarter'    => 'nullable|integer|min:1|max:4',
            'price_source'        => 'nullable|string|max:100',
            'price_validity_date' => 'nullable|date',
        ], $this->monthValidation()));

        $data['ppmp_id']      = $ppmp->id;
        $data['is_ps_dbm']    = false;
        $data['catalogue_id'] = null;
        $data['sort_order']   = $ppmp->items()->whereNull('catalogue_id')->count();

        foreach (PpmpItem::MONTHS as $m) {
            $data[$m] = $data[$m] ?? 0;
        }

        PpmpItem::create($data);

        return back()->with('success', 'Item added (Part II).');
    }

    /**
     * Update a line item.
     *   Part I catalogue items:  only schedule / fund / quarter / remarks editable
     *   Part II catalogue items: office_unit_cost + schedule + procurement fields (description/unit locked)
     *   Manual items:            full edit
     */
    public function update(Request $request, Ppmp $ppmp, PpmpItem $item)
    {
        $this->authorize('update', $ppmp);
        abort_if($item->ppmp_id !== $ppmp->id, 404);

        if ($item->catalogue_id) {
            $cat = $item->catalogue ?? PpmpCatalogue::find($item->catalogue_id);

            if (!$cat || $cat->part === 1) {
                // Part I: schedule-only
                $data = $request->validate(array_merge([
                    'fund_source'         => 'required|in:' . implode(',', array_keys(PpmpItem::FUND_SOURCES)),
                    'procurement_quarter' => 'nullable|integer|min:1|max:4',
                    'delivery_quarter'    => 'nullable|integer|min:1|max:4',
                    'remarks'             => 'nullable|string|max:1000',
                ], $this->monthValidation()));

                foreach (PpmpItem::MONTHS as $m) {
                    $data[$m] = $data[$m] ?? 0;
                }

                $item->update($data);
                return back()->with('success', 'PS-DBM item schedule updated.');
            }

            // Part II catalogue: allow price + scheduling fields
            $data = $request->validate(array_merge([
                'office_unit_cost'    => 'required|numeric|min:0.01',
                'fund_source'         => 'required|in:' . implode(',', array_keys(PpmpItem::FUND_SOURCES)),
                'procurement_method'  => 'required|in:' . implode(',', array_keys(PpmpItem::PROCUREMENT_METHODS)),
                'procurement_quarter' => 'nullable|integer|min:1|max:4',
                'delivery_quarter'    => 'nullable|integer|min:1|max:4',
                'remarks'             => 'nullable|string|max:1000',
                'price_source'        => 'nullable|string|max:100',
                'price_validity_date' => 'nullable|date',
            ], $this->monthValidation()));

            foreach (PpmpItem::MONTHS as $m) {
                $data[$m] = $data[$m] ?? 0;
            }

            $item->update($data);
            return back()->with('success', 'Part II catalogue item updated.');
        }

        // Manual: full edit
        $data = $request->validate(array_merge([
            'code'                => 'nullable|string|max:50',
            'description'         => 'required|string|max:500',
            'unit'                => 'required|string|max:50',
            'category'            => 'required|in:' . implode(',', PpmpItem::CATEGORIES),
            'unit_cost'           => 'required|numeric|min:0.01',
            'procurement_method'  => 'required|in:' . implode(',', array_keys(PpmpItem::PROCUREMENT_METHODS)),
            'remarks'             => 'nullable|string|max:1000',
            'fund_source'         => 'required|in:' . implode(',', array_keys(PpmpItem::FUND_SOURCES)),
            'procurement_quarter' => 'nullable|integer|min:1|max:4',
            'delivery_quarter'    => 'nullable|integer|min:1|max:4',
            'price_source'        => 'nullable|string|max:100',
            'price_validity_date' => 'nullable|date',
        ], $this->monthValidation()));

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
