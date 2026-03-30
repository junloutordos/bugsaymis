<?php

namespace App\Http\Controllers;

use App\Models\Pds;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Http\Request;

class PDSController extends Controller
{
    // ... other methods

    public function exportPDS(Pds $pds)
    {
        $this->authorizeAccess($pds);

        // Load template
        $templatePath = storage_path('app/public/templates/pds_template2025.xlsx');
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Fill Personal Info
        $sheet->setCellValue('B5', $pds->personalInfo->surname ?? '');
        $sheet->setCellValue('C5', $pds->personalInfo->first_name ?? '');
        $sheet->setCellValue('D5', $pds->personalInfo->middle_name ?? '');

        // Fill Family Background
        $sheet->setCellValue('B15', $pds->familyBackground->father_surname ?? '');
        $sheet->setCellValue('C15', $pds->familyBackground->father_first_name ?? '');
        $sheet->setCellValue('B16', $pds->familyBackground->mother_surname ?? '');
        $sheet->setCellValue('C16', $pds->familyBackground->mother_first_name ?? '');

        // Fill Education
        $row = 25;
        foreach ($pds->education ?? [] as $edu) {
            $sheet->setCellValue('B'.$row, $edu->level);
            $sheet->setCellValue('C'.$row, $edu->school_name);
            $sheet->setCellValue('D'.$row, $edu->degree_course);
            $sheet->setCellValue('E'.$row, $edu->year_graduated);
            $sheet->setCellValue('F'.$row, $edu->highest_level);
            $sheet->setCellValue('G'.$row, $edu->scholarship);
            $row++;
        }

        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'pds');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempFile);

        // Return download response
        return response()->download($tempFile, 'PDS_'.$pds->id.'.xlsx')->deleteFileAfterSend(true);
    }
}
