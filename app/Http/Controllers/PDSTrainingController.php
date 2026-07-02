<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
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

        $header = array_map(fn ($value) => trim((string) $value), $header);
        $count = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($row) !== count($header)) {
                continue;
            }

            $rowData = array_combine($header, $row);

            $dateFrom = $this->parseCsvDate($rowData['date_from'] ?? null);
            $dateTo   = $this->parseCsvDate($rowData['date_to'] ?? null);
            if ($dateFrom === false || $dateTo === false) {
                $skipped++;
                continue;
            }

            $pds->trainings()->create([
                'training_title' => $rowData['training_title'] ?? null,
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo,
                'hours'          => isset($rowData['hours']) && $rowData['hours'] !== '' ? $rowData['hours'] : null,
                'training_type'  => $rowData['training_type'] ?? null,
                'conducted_by'   => $rowData['conducted_by'] ?? null,
            ]);

            $count++;
        }

        fclose($handle);

        if ($count === 0) {
            return back()->withErrors(['file' => $skipped > 0
                ? "No trainings uploaded — {$skipped} row(s) have unrecognized dates. Use YYYY-MM-DD (e.g. 2026-06-08)."
                : 'No valid trainings found in CSV.']);
        }

        $message = "{$count} training(s) successfully uploaded!";
        if ($skipped > 0) {
            $message .= " {$skipped} row(s) skipped due to unrecognized dates — use YYYY-MM-DD.";
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
