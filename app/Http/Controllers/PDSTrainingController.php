<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pds;

class PDSTrainingController extends Controller
{
    public function uploadCSV(Request $request, PDS $pds)
    {
        $data = $request->validate([
            'csv_base64'   => 'nullable|string',
            'csv_filename' => 'nullable|string|max:255',
            'csv_mime'     => 'nullable|string|max:100',
            'file'         => 'nullable|file|mimes:csv,txt',
        ]);

        if (empty($data['csv_base64']) && ! $request->hasFile('file')) {
            return back()->withErrors(['file' => 'Please choose a CSV file.']);
        }

        $contents = $this->csvContents($request, $data);
        // Excel's "CSV UTF-8" variant prepends a BOM that would corrupt the
        // first header name (and with it every training_title lookup).
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        if (trim($contents) === '') {
            return back()->withErrors(['file' => 'CSV file is empty.']);
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            return back()->withErrors(['file' => 'CSV file is empty.']);
        }

        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
        $rows = [];
        $skippedDates = 0;
        $skippedTitle = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($row) !== count($header)) {
                continue;
            }

            $rowData = array_combine($header, $row);

            // training_title is NOT NULL in the schema — skip instead of 500
            $title = mb_substr(trim((string) ($rowData['training_title'] ?? '')), 0, 255);
            if ($title === '') {
                $skippedTitle++;
                continue;
            }

            $dateFrom = $this->parseCsvDate($rowData['date_from'] ?? null);
            $dateTo   = $this->parseCsvDate($rowData['date_to'] ?? null);
            if ($dateFrom === false || $dateTo === false) {
                $skippedDates++;
                continue;
            }

            $rows[] = [
                'training_title' => $title,
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo,
                'hours'          => $this->parseCsvHours($rowData['hours'] ?? null),
                'training_type'  => mb_substr(trim((string) ($rowData['training_type'] ?? '')), 0, 255) ?: null,
                'conducted_by'   => mb_substr(trim((string) ($rowData['conducted_by'] ?? '')), 0, 255) ?: null,
            ];
        }

        fclose($handle);

        $note = $this->skippedNote($skippedDates, $skippedTitle);

        if (count($rows) === 0) {
            return back()->withErrors(['file' => $note
                ? "No trainings uploaded. {$note}"
                : 'No valid trainings found in CSV.']);
        }

        // All rows or none — a mid-import failure must not leave partial data
        DB::transaction(function () use ($pds, $rows) {
            foreach ($rows as $row) {
                $pds->trainings()->create($row);
            }
        });

        $message = count($rows) . ' training(s) successfully uploaded!';
        if ($note) {
            $message .= " {$note}";
        }

        return back()->with('success', $message);
    }

    /**
     * Normalize a CSV date to Y-m-d. Returns null for blank values and
     * false when the value can't be parsed (row should be skipped).
     */
    private function parseCsvDate(mixed $value): string|false|null
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y', 'n-j-Y'] as $format) {
            if (Carbon::hasFormat($value, $format)) {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            }
        }

        return false;
    }

    /**
     * Extract a usable numeric value from a free-text hours cell
     * ("24 HOURS" → 24.0). Null when absent or outside decimal(5,2) range.
     */
    private function parseCsvHours(mixed $value): ?float
    {
        if (preg_match('/\d+(\.\d+)?/', (string) $value, $matches)) {
            $hours = (float) $matches[0];

            return $hours <= 999.99 ? $hours : null;
        }

        return null;
    }

    private function skippedNote(int $badDates, int $missingTitle): ?string
    {
        $parts = [];
        if ($badDates > 0) {
            $parts[] = "{$badDates} row(s) skipped — unrecognized dates, use YYYY-MM-DD (e.g. 2026-06-08)";
        }
        if ($missingTitle > 0) {
            $parts[] = "{$missingTitle} row(s) skipped — missing training title";
        }

        return $parts ? implode('; ', $parts) . '.' : null;
    }

    private function csvContents(Request $request, array $data): string
    {
        if (! empty($data['csv_base64'])) {
            $encoded = preg_replace('/^data:[^;]+;base64,/', '', $data['csv_base64']);
            $decoded = base64_decode($encoded, true);
            abort_if($decoded === false, 422, 'Invalid CSV file data.');

            return $decoded;
        }

        return file_get_contents($request->file('file')->getRealPath()) ?: '';
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="pds_training_template.csv"',
        ];

        $columns = ['training_title', 'date_from', 'date_to', 'hours', 'training_type', 'conducted_by'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
