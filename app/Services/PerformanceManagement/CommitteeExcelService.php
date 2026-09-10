<?php

namespace App\Services\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Committee roster/compliance export — direct PhpSpreadsheet, mirroring
 * ClassRecordExcelService's convention rather than the Maatwebsite facade.
 */
class CommitteeExcelService
{
    public function exportRoster(Committee $committee, ?int $termId = null): string
    {
        $term = $termId ? AcademicTerm::find($termId) : AcademicTerm::where('is_current', true)->first();

        $committee->loadMissing(['head', 'members']);

        $assignments = FacultyCommitteeAssignment::with(['faculty:id,name,position'])
            ->where('committee_id', $committee->id)
            ->when($term, fn ($q) => $q->where('academic_term_id', $term->id))
            ->where('status', 'active')
            ->orderByRaw("CASE role WHEN 'chairperson' THEN 0 WHEN 'co_chair' THEN 1 WHEN 'secretary' THEN 2 ELSE 3 END")
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Roster');

        $sheet->setCellValue('A1', $committee->name . ' — Committee Roster');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Term: ' . ($term?->full_label ?? 'All'));
        $sheet->mergeCells('A2:E2');
        if ($committee->so_number) {
            $sheet->setCellValue('A3', 'SO Number: ' . $committee->so_number);
            $sheet->mergeCells('A3:E3');
        }

        $headerRow = 5;
        $headers = ['Name', 'Position', 'Role', 'Load Units', 'Status'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$headerRow}", $h);
        }
        $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        $row = $headerRow + 1;
        foreach ($assignments as $a) {
            $sheet->setCellValue("A{$row}", $a->faculty?->name ?? '—');
            $sheet->setCellValue("B{$row}", $a->faculty?->position ?? '—');
            $sheet->setCellValue("C{$row}", ucfirst(str_replace('_', ' ', $a->role)));
            $sheet->setCellValue("D{$row}", (float) $a->load_units);
            $sheet->setCellValue("E{$row}", ucfirst($a->status));
            $row++;
        }

        $sheet->getStyle("A{$headerRow}:E" . ($row - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->saveTempFile($spreadsheet);
    }

    private function saveTempFile(Spreadsheet $spreadsheet): string
    {
        $path = sys_get_temp_dir() . '/committee_roster_' . uniqid() . '.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($path);

        return $path;
    }
}
