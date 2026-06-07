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

    public function downloadTemplate()
    {
        $this->authorize('property.manage');

        $headers = ['property_number', 'description', 'category_name', 'unit', 'quantity', 'unit_cost', 'acquisition_date', 'acquisition_mode', 'brand', 'model', 'serial_number', 'location', 'accountable_officer', 'status'];
        $example1 = ['', 'Desktop Computer', 'IT Equipment and Software', 'unit', '1', '45000.00', '2023-01-15', 'purchase', 'Dell', 'OptiPlex 7090', 'SN-123456', 'Computer Lab 1', 'Juan dela Cruz', 'serviceable'];
        $example2 = ['PROP-2022-001', 'Office Chair', 'Furniture and Fixtures', 'unit', '1', '3500.00', '2022-06-01', 'purchase', '', '', '', 'Principal Office', 'Maria Santos', 'serviceable'];

        $lines = [implode(',', $headers), implode(',', $example1), implode(',', $example2)];

        return response(implode("\n", $lines), 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="property_items_template.csv"',
        ]);
    }

    public function previewImport(Request $request)
    {
        $this->authorize('property.manage');

        $request->validate(['csv_base64' => 'required|string']);

        $csv = base64_decode($request->csv_base64);
        $rows = array_filter(array_map('str_getcsv', explode("\n", trim($csv))));
        $rows = array_values($rows);

        if (count($rows) < 2) {
            return response()->json(['error' => 'CSV must have a header row and at least one data row.'], 422);
        }

        $headers = array_map('trim', $rows[0]);
        $required = ['description', 'category_name', 'unit', 'quantity', 'unit_cost', 'acquisition_date'];
        foreach ($required as $col) {
            if (! in_array($col, $headers)) {
                return response()->json(['error' => "Missing required column: {$col}"], 422);
            }
        }

        $categoryMap = PropertyCategory::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id])->toArray();
        $officerMap  = User::where('status', '<>', 'inactive')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id])->toArray();
        $existingNumbers = PropertyItem::pluck('id', 'property_number')->toArray();

        $preview = [];
        foreach (array_slice($rows, 1) as $i => $cols) {
            $row = [];
            foreach ($headers as $j => $h) {
                $row[$h] = trim($cols[$j] ?? '');
            }

            $errors = [];
            if (empty($row['description'])) $errors[] = 'description is required';
            if (empty($row['unit'])) $errors[] = 'unit is required';
            if (! is_numeric($row['quantity'] ?? '')) $errors[] = 'quantity must be a number';
            if (! is_numeric($row['unit_cost'] ?? '')) $errors[] = 'unit_cost must be a number';
            if (empty($row['acquisition_date'])) $errors[] = 'acquisition_date is required';

            $categoryId = $categoryMap[strtolower($row['category_name'] ?? '')] ?? null;
            if (! $categoryId) $errors[] = "category_name '{$row['category_name']}' not found";

            $officerName = $row['accountable_officer'] ?? '';
            $officerId   = $officerMap[strtolower($officerName)] ?? null;
            $officerMatched = empty($officerName) || (bool) $officerId;

            $propNum = $row['property_number'] ?? '';
            $action  = ($propNum && isset($existingNumbers[$propNum])) ? 'update' : 'create';

            $preview[] = [
                'row'              => $i + 2,
                'property_number'  => $propNum ?: '(auto)',
                'description'      => $row['description'],
                'category_name'    => $row['category_name'],
                'category_matched' => (bool) $categoryId,
                'unit'             => $row['unit'],
                'quantity'         => $row['quantity'],
                'unit_cost'        => $row['unit_cost'],
                'acquisition_date' => $row['acquisition_date'],
                'acquisition_mode' => $row['acquisition_mode'] ?? 'purchase',
                'brand'            => $row['brand'] ?? '',
                'model'            => $row['model'] ?? '',
                'serial_number'    => $row['serial_number'] ?? '',
                'location'         => $row['location'] ?? '',
                'accountable_officer' => $officerName,
                'officer_matched'  => $officerMatched,
                'status'           => in_array($row['status'] ?? '', ['serviceable','unserviceable']) ? $row['status'] : 'serviceable',
                'action'           => $action,
                'errors'           => $errors,
            ];
        }

        return response()->json(['preview' => $preview]);
    }

    public function importCsv(Request $request)
    {
        $this->authorize('property.manage');

        $request->validate(['csv_base64' => 'required|string']);

        $csv = base64_decode($request->csv_base64);
        $rows = array_filter(array_map('str_getcsv', explode("\n", trim($csv))));
        $rows = array_values($rows);
        $headers = array_map('trim', $rows[0]);

        $categoryMap = PropertyCategory::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id])->toArray();
        $officerMap  = User::where('status', '<>', 'inactive')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id])->toArray();
        $userId = $request->user()->id;

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        DB::transaction(function () use ($rows, $headers, $categoryMap, $officerMap, $userId, &$imported, &$skipped, &$errors) {
            foreach (array_slice($rows, 1) as $i => $cols) {
                $row = [];
                foreach ($headers as $j => $h) {
                    $row[$h] = trim($cols[$j] ?? '');
                }

                $rowNum = $i + 2;
                $categoryId = $categoryMap[strtolower($row['category_name'] ?? '')] ?? null;

                if (empty($row['description']) || empty($row['unit']) || ! $categoryId
                    || ! is_numeric($row['quantity'] ?? '')
                    || ! is_numeric($row['unit_cost'] ?? '')
                    || empty($row['acquisition_date'])) {
                    $errors[] = "Row {$rowNum} skipped — missing required fields or unmatched category.";
                    $skipped++;
                    continue;
                }

                $officerName = $row['accountable_officer'] ?? '';
                $officerId   = $officerName ? ($officerMap[strtolower($officerName)] ?? null) : null;

                $propNum = $row['property_number'] ?? '';
                $mode    = in_array($row['acquisition_mode'] ?? '', ['purchase','donation','transfer','fabricated']) ? $row['acquisition_mode'] : 'purchase';
                $status  = in_array($row['status'] ?? '', ['serviceable','unserviceable']) ? $row['status'] : 'serviceable';

                $data = [
                    'description'        => $row['description'],
                    'category_id'        => $categoryId,
                    'unit'               => $row['unit'],
                    'quantity'           => (float) $row['quantity'],
                    'unit_cost'          => (float) $row['unit_cost'],
                    'acquisition_date'   => $row['acquisition_date'],
                    'acquisition_mode'   => $mode,
                    'brand'              => $row['brand'] ?? null ?: null,
                    'model'              => $row['model'] ?? null ?: null,
                    'serial_number'      => $row['serial_number'] ?? null ?: null,
                    'location'           => $row['location'] ?? null ?: null,
                    'current_officer_id' => $officerId,
                    'status'             => $status,
                    'created_by'         => $userId,
                ];

                if ($propNum) {
                    PropertyItem::updateOrCreate(['property_number' => $propNum], $data);
                } else {
                    $data['property_number'] = PropertyItem::generateNumber('PROP');
                    PropertyItem::create($data);
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
