<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Models\Supply\SupplyCategory;
use App\Models\Supply\SupplyItem;
use App\Models\Supply\SupplyStockCard;
use App\Models\Supply\SupplyLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function downloadTemplate()
    {
        $this->authorize('supply.manage');

        $headers = ['item_code', 'description', 'category_name', 'unit', 'opening_quantity', 'average_unit_cost', 'reorder_level'];
        $example1 = ['', 'Bond Paper (Short)', 'Office Supplies - Consumable', 'ream', '50', '180.00', '10'];
        $example2 = ['', 'Ballpen (Black)', 'Office Supplies - Consumable', 'box', '20', '85.00', '5'];

        $lines = [implode(',', $headers), implode(',', $example1), implode(',', $example2)];

        return response(implode("\n", $lines), 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="supply_items_template.csv"',
        ]);
    }

    public function previewImport(Request $request)
    {
        $this->authorize('supply.manage');

        $request->validate(['csv_base64' => 'required|string']);

        $csv = base64_decode($request->csv_base64);
        $rows = array_filter(array_map('str_getcsv', explode("\n", trim($csv))));
        $rows = array_values($rows);

        if (count($rows) < 2) {
            return response()->json(['error' => 'CSV must have a header row and at least one data row.'], 422);
        }

        $headers = array_map('trim', $rows[0]);
        $required = ['description', 'category_name', 'unit', 'opening_quantity', 'average_unit_cost'];
        foreach ($required as $col) {
            if (! in_array($col, $headers)) {
                return response()->json(['error' => "Missing required column: {$col}"], 422);
            }
        }

        $categoryMap = SupplyCategory::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id])->toArray();
        $existingCodes = SupplyItem::pluck('id', 'item_code')->toArray();

        $preview = [];
        foreach (array_slice($rows, 1) as $i => $cols) {
            $row = [];
            foreach ($headers as $j => $h) {
                $row[$h] = trim($cols[$j] ?? '');
            }

            $errors = [];
            if (empty($row['description'])) $errors[] = 'description is required';
            if (empty($row['unit'])) $errors[] = 'unit is required';
            if (! is_numeric($row['opening_quantity'] ?? '')) $errors[] = 'opening_quantity must be a number';
            if (! is_numeric($row['average_unit_cost'] ?? '')) $errors[] = 'average_unit_cost must be a number';

            $categoryId = $categoryMap[strtolower($row['category_name'] ?? '')] ?? null;
            if (! $categoryId) $errors[] = "category_name '{$row['category_name']}' not found";

            $itemCode = $row['item_code'] ?? '';
            $action = ($itemCode && isset($existingCodes[$itemCode])) ? 'update' : 'create';

            $preview[] = [
                'row'              => $i + 2,
                'item_code'        => $itemCode ?: '(auto)',
                'description'      => $row['description'],
                'category_name'    => $row['category_name'],
                'category_matched' => (bool) $categoryId,
                'unit'             => $row['unit'],
                'opening_quantity' => $row['opening_quantity'],
                'average_unit_cost'=> $row['average_unit_cost'],
                'reorder_level'    => $row['reorder_level'] ?? 0,
                'action'           => $action,
                'errors'           => $errors,
            ];
        }

        return response()->json(['preview' => $preview]);
    }

    public function importCsv(Request $request)
    {
        $this->authorize('supply.manage');

        $request->validate(['csv_base64' => 'required|string']);

        $csv = base64_decode($request->csv_base64);
        $rows = array_filter(array_map('str_getcsv', explode("\n", trim($csv))));
        $rows = array_values($rows);
        $headers = array_map('trim', $rows[0]);

        $categoryMap = SupplyCategory::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id])->toArray();
        $userId = $request->user()->id;

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        DB::transaction(function () use ($rows, $headers, $categoryMap, $userId, &$imported, &$skipped, &$errors) {
            foreach (array_slice($rows, 1) as $i => $cols) {
                $row = [];
                foreach ($headers as $j => $h) {
                    $row[$h] = trim($cols[$j] ?? '');
                }

                $rowNum = $i + 2;
                $categoryId = $categoryMap[strtolower($row['category_name'] ?? '')] ?? null;

                if (empty($row['description']) || empty($row['unit']) || ! $categoryId
                    || ! is_numeric($row['opening_quantity'] ?? '')
                    || ! is_numeric($row['average_unit_cost'] ?? '')) {
                    $errors[] = "Row {$rowNum} skipped — missing required fields or unmatched category.";
                    $skipped++;
                    continue;
                }

                $itemCode = $row['item_code'] ?? '';
                $item = $itemCode ? SupplyItem::where('item_code', $itemCode)->first() : null;

                if ($item) {
                    $item->update([
                        'description'         => $row['description'],
                        'category_id'         => $categoryId,
                        'unit'                => $row['unit'],
                        'reorder_point'       => is_numeric($row['reorder_level'] ?? '') ? (int) $row['reorder_level'] : 0,
                    ]);
                } else {
                    $item = SupplyItem::create([
                        'item_code'           => $itemCode ?: SupplyItem::generateCode($categoryId),
                        'description'         => $row['description'],
                        'category_id'         => $categoryId,
                        'unit'                => $row['unit'],
                        'estimated_unit_cost' => (float) $row['average_unit_cost'],
                        'reorder_point'       => is_numeric($row['reorder_level'] ?? '') ? (int) $row['reorder_level'] : 0,
                        'is_active'           => true,
                    ]);
                }

                $qty  = (float) $row['opening_quantity'];
                $cost = (float) $row['average_unit_cost'];

                if ($qty > 0) {
                    $card = SupplyStockCard::firstOrCreate(
                        ['supply_item_id' => $item->id],
                        ['balance_quantity' => 0, 'average_unit_cost' => 0]
                    );

                    $card->update(['balance_quantity' => $qty, 'average_unit_cost' => $cost]);

                    SupplyLedgerEntry::create([
                        'supply_item_id'   => $item->id,
                        'transaction_date' => today(),
                        'reference_type'   => 'OPENING',
                        'reference_id'     => 0,
                        'reference_number' => 'OPENING-BALANCE',
                        'qty_received'     => $qty,
                        'qty_issued'       => 0,
                        'qty_returned'     => 0,
                        'balance_after'    => $qty,
                        'unit_cost'        => $cost,
                        'total_cost'       => round($qty * $cost, 2),
                        'remarks'          => 'Opening balance from CSV import',
                        'created_by'       => $userId,
                    ]);
                }

                $imported++;
            }
        });

        return response()->json([
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
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
