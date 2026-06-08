<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\PPMP\PpmpCatalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

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
            ->orderBy('part')
            ->orderBy('row_position')
            ->get();

        $availableYears = PpmpCatalogue::distinct()->pluck('fiscal_year')
            ->push((int) date('Y'))
            ->unique()->sort()->values();

        $part1Count = $items->where('part', 1)->count();
        $part2Count = $items->where('part', 2)->count();

        return Inertia::render('PPMP/Catalogue/Index', [
            'items'          => $items,
            'fiscalYear'     => $fiscalYear,
            'availableYears' => $availableYears,
            'part1Count'     => $part1Count,
            'part2Count'     => $part2Count,
        ]);
    }

    /**
     * Parse and upsert the official APP-CSE Excel template from PS-DBM.
     *
     * Expected format (single sheet "APP-CSE YYYY FORM"):
     *   Row structure from row ~32 onward:
     *     Category group header row: col A = text (non-numeric), col B = empty
     *     Item row: col A = seq# (numeric), col B = UNSPSC code, col C = description,
     *               col D = unit, col Z (index 25) = unit price (Part I only)
     *   Part boundary: a row where col A starts with "PART II"
     */
    public function upload(Request $request)
    {
        ini_set('memory_limit', '512M');

        $request->validate([
            'fiscal_year' => 'required|integer|min:2020|max:2099',
            'file_base64' => 'required|string',
        ]);

        $fiscalYear = (int) $request->input('fiscal_year');
        $base64     = $request->input('file_base64');

        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64, 2)[1];
        }

        $decoded = base64_decode($base64, strict: true);
        if ($decoded === false) {
            return back()->with('error', 'Invalid file encoding. Please re-select the file.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'appcse_') . '.xlsx';
        file_put_contents($tmpFile, $decoded);

        try {
            $reader = new XlsxReader();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmpFile);
            $ws          = $this->findDataSheet($spreadsheet);
        } catch (\Throwable $e) {
            @unlink($tmpFile);
            Log::error('PPMP catalogue upload: failed to load spreadsheet', ['error' => $e->getMessage()]);
            return back()->with('error', 'Could not read file: ' . $e->getMessage());
        } finally {
            @unlink($tmpFile);
        }

        $user = $request->user();

        try {
            $results = $this->parseAppCse($ws, $fiscalYear, $user->id);
        } catch (\Throwable $e) {
            Log::error('PPMP catalogue upload: parsing failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Upload failed during parsing: ' . $e->getMessage());
        }

        $msg = "APP-CSE uploaded for FY {$fiscalYear}: "
             . "{$results['part1Count']} Part I items, "
             . "{$results['part2Count']} Part II items saved.";

        if ($results['skipped']) {
            $msg .= " {$results['skipped']} row(s) skipped.";
        }

        return back()->with('success', $msg)->with('upload_errors', $results['errors']);
    }

    /**
     * Find the data sheet — prefer one whose name contains "APP" and "CSE",
     * fall back to the first sheet so any sheet order works.
     */
    private function findDataSheet($spreadsheet)
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if (preg_match('/APP.{0,3}CSE/i', $sheet->getTitle())) {
                return $sheet;
            }
        }
        return $spreadsheet->getSheet(0);
    }

    /**
     * Parse the APP-CSE worksheet and upsert into ppmp_catalogue.
     */
    private function parseAppCse($ws, int $fiscalYear, int $uploadedBy): array
    {
        $part          = 1;
        $categoryGroup = null;
        $rowPosition   = 0;
        $part1Count    = 0;
        $part2Count    = 0;
        $skipped       = 0;
        $errors        = [];

        // Deactivate all existing items for this year before re-import
        PpmpCatalogue::where('fiscal_year', $fiscalYear)->update(['is_active' => false]);

        foreach ($ws->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex();
            $cells    = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(false);

            $vals = [];
            foreach ($cells as $cell) {
                $vals[] = $cell->getValue();
            }

            // Pad to at least 27 columns
            while (count($vals) < 27) {
                $vals[] = null;
            }

            $colA = trim((string) ($vals[0] ?? ''));
            $colB = trim((string) ($vals[1] ?? ''));
            $colC = trim((string) ($vals[2] ?? ''));
            $colD = trim((string) ($vals[3] ?? ''));
            $colZ = $vals[25] ?? null; // Unit price

            // Detect Part II boundary
            if (str_starts_with(strtoupper($colA), 'PART II')) {
                $part = 2;
                $categoryGroup = null;
                continue;
            }

            // Skip header / instruction rows before data starts
            if ($rowIndex < 32) {
                continue;
            }

            // Skip summary rows (A, B, C, D, E labels)
            if (preg_match('/^[A-E]\.\s/i', $colA)) {
                continue;
            }

            // Category group header: col A is non-empty text, col B is empty, not a number
            if ($colA !== '' && $colB === '' && !is_numeric($colA)) {
                $categoryGroup = $colA;
                continue;
            }

            // Item row: col A is a sequential number
            if (!is_numeric($colA) || $colA === '' || $colB === '' || $colC === '') {
                continue;
            }

            $stockNumber = $colB;
            $description = $colC;
            $unit        = $colD !== '' ? $colD : 'piece';
            $unitCost    = is_numeric($colZ) ? (float) $colZ : 0.0;
            $isPriceFixed = ($part === 1);
            $rowPosition++;

            if (empty($stockNumber) || empty($description)) {
                $skipped++;
                continue;
            }

            try {
                PpmpCatalogue::updateOrCreate(
                    ['fiscal_year' => $fiscalYear, 'stock_number' => $stockNumber],
                    [
                        'part'                => $part,
                        'category_group'      => $categoryGroup,
                        'row_position'        => $rowPosition,
                        'is_price_fixed'      => $isPriceFixed,
                        'description'         => $description,
                        'unit'                => $unit,
                        'unit_cost'           => $unitCost,
                        'price_validity_date' => null,
                        'is_active'           => true,
                        'uploaded_by'         => $uploadedBy,
                        'uploaded_at'         => now(),
                    ]
                );

                if ($part === 1) {
                    $part1Count++;
                } else {
                    $part2Count++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowIndex} ({$stockNumber}): " . $e->getMessage();
                $skipped++;
            }
        }

        return compact('part1Count', 'part2Count', 'skipped', 'errors');
    }

    /**
     * Search catalogue items — JSON endpoint used by picker modals.
     * part=1 → Part I (PS-DBM items, price fixed)
     * part=2 → Part II (non-PS-DBM pre-defined, price to be filled)
     * part=0 → all parts
     */
    public function search(Request $request)
    {
        $fiscalYear = (int) $request->input('fiscal_year', (int) date('Y'));
        $query      = trim((string) $request->input('q', ''));
        $part       = $request->input('part'); // null = all

        $items = PpmpCatalogue::forYear($fiscalYear)
            ->when($part !== null, fn ($q) => $q->part((int) $part))
            ->when($query !== '', function ($q) use ($query) {
                $like = '%' . $query . '%';
                $q->where(function ($sub) use ($like) {
                    $sub->where('stock_number', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('category_group', 'like', $like);
                });
            })
            ->orderBy('part')
            ->orderBy('row_position')
            ->limit(150)
            ->get(['id', 'part', 'category_group', 'stock_number', 'description', 'unit', 'unit_cost', 'is_price_fixed', 'price_validity_date']);

        return response()->json($items);
    }
}
