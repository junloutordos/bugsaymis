<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pds;

class PDSTrainingController extends Controller
{
    public function uploadCSV(Request $request, PDS $pds)
{
    $request->validate([
        'file' => 'required|file|mimes:csv,txt',
    ]);

    $file = $request->file('file');
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $csvData = array_map('str_getcsv', $lines);

    if (empty($csvData)) {
        return back()->with('error', 'CSV file is empty.');
    }

    $header = array_shift($csvData);
    $count = 0;

    foreach ($csvData as $row) {
        if (count($row) !== count($header)) continue;

        $data = array_combine($header, $row);

        $pds->trainings()->create([
            'training_title' => $data['training_title'] ?? null,
            'date_from' => !empty($data['date_from']) ? $data['date_from'] : null,
            'date_to' => !empty($data['date_to']) ? $data['date_to'] : null,
            'hours' => isset($data['hours']) && $data['hours'] !== '' ? $data['hours'] : null,
            'training_type' => $data['training_type'] ?? null,
            'conducted_by' => $data['conducted_by'] ?? null,
        ]);

        $count++;
    }

    if ($count === 0) {
        return back()->with('error', 'No valid trainings found in CSV.');
    }

    return back()->with('success', "$count training(s) successfully uploaded!");
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
        fputcsv($file, $columns); // Add header row
        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
}


}
