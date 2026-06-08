<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\PPMP\PpmpCatalogue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PPMPCatalogueController extends Controller
{
    /**
     * List uploaded catalogues per fiscal year.
     */
    public function index(Request $request)
    {
        $fiscalYear = $request->input('fiscal_year', (int) date('Y'));

        $items = PpmpCatalogue::with('uploader:id,name')
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('stock_number')
            ->get();

        $availableYears = PpmpCatalogue::distinct()->pluck('fiscal_year')
            ->push((int) date('Y'))
            ->unique()->sort()->values();

        return Inertia::render('PPMP/Catalogue/Index', [
            'items'          => $items,
            'fiscalYear'     => $fiscalYear,
            'availableYears' => $availableYears,
            'itemCount'      => $items->count(),
        ]);
    }

    /**
     * Parse and upsert a base64-encoded Excel/CSV catalogue upload.
     *
     * Expected columns (row 1 = header, skipped):
     *   A: Stock Number  B: Description  C: Unit  D: Unit Cost  E: Price Validity Date (optional)
     */
    public function upload(Request $request)
    {
        $request->validate([
            'fiscal_year' => 'required|integer|min:2020|max:2099',
            'file_base64' => 'required|string',
        ]);

        $fiscalYear = (int) $request->input('fiscal_year');
        $base64     = $request->input('file_base64');

        // Strip data URI prefix if present
        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64, 2)[1];
        }

        $decoded = base64_decode($base64, strict: true);
        if ($decoded === false) {
            return back()->with('error', 'Invalid file encoding. Please re-select the file.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'ppmp_cat_') . '.xlsx';
        file_put_contents($tmpFile, $decoded);

        try {
            $spreadsheet = IOFactory::load($tmpFile);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            @unlink($tmpFile);
            return back()->with('error', 'Could not read file: ' . $e->getMessage());
        } finally {
            @unlink($tmpFile);
        }

        $user     = $request->user();
        $inserted = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $rowIndex => $row) {
            // Skip header row
            if ($rowIndex === 0) {
                continue;
            }

            [$stockNumber, $description, $unit, $unitCost, $validityDate] = array_pad($row, 5, null);

            $stockNumber = trim((string) $stockNumber);
            $description = trim((string) $description);
            $unit        = trim((string) $unit);

            if ($stockNumber === '' || $description === '' || $unit === '') {
                $skipped++;
                continue;
            }

            $costValue = is_numeric($unitCost) ? (float) $unitCost : 0;
            if ($costValue <= 0) {
                $errors[] = "Row " . ($rowIndex + 1) . ": '{$stockNumber}' has invalid unit cost — skipped.";
                $skipped++;
                continue;
            }

            $validityDateParsed = null;
            if ($validityDate) {
                try {
                    $validityDateParsed = \Carbon\Carbon::parse($validityDate)->toDateString();
                } catch (\Throwable) {
                    // non-fatal, leave null
                }
            }

            PpmpCatalogue::updateOrCreate(
                ['fiscal_year' => $fiscalYear, 'stock_number' => $stockNumber],
                [
                    'description'         => $description,
                    'unit'                => $unit,
                    'unit_cost'           => $costValue,
                    'price_validity_date' => $validityDateParsed,
                    'is_active'           => true,
                    'uploaded_by'         => $user->id,
                    'uploaded_at'         => now(),
                ]
            );

            $inserted++;
        }

        $message = "Catalogue uploaded: {$inserted} item(s) saved for FY {$fiscalYear}.";
        if ($skipped) {
            $message .= " {$skipped} row(s) skipped.";
        }

        return back()->with('success', $message)->with('upload_errors', $errors);
    }

    /**
     * Search catalogue items — JSON, used by the Part I picker modal.
     */
    public function search(Request $request)
    {
        $fiscalYear = (int) $request->input('fiscal_year', (int) date('Y'));
        $query      = trim((string) $request->input('q', ''));

        $items = PpmpCatalogue::forYear($fiscalYear)
            ->when($query !== '', function ($q) use ($query) {
                $like = '%' . $query . '%';
                $q->where(function ($sub) use ($like) {
                    $sub->where('stock_number', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->orderBy('stock_number')
            ->limit(100)
            ->get(['id', 'stock_number', 'description', 'unit', 'unit_cost', 'price_validity_date']);

        return response()->json($items);
    }
}
