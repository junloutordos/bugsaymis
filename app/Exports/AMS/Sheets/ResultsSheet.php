<?php

namespace App\Exports\AMS\Sheets;

use App\Exports\AMS\Concerns\FormatsLikertOptions;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One row per question (+ a bold "Section Average" row per section), for a
 * single evaluation-summary section list as returned by
 * ActivityEvaluationSummaryService::build()['sections'].
 */
class ResultsSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    use FormatsLikertOptions;

    private array $boldRows = [];

    public function __construct(private array $sections, private string $sheetTitle = 'Results') {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        return ['Section', '#', 'Question', 'Average', 'N (Scored)', 'Distribution'];
    }

    public function array(): array
    {
        $rows     = [];
        $sheetRow = 2; // row 1 is the heading row

        foreach ($this->sections as $section) {
            $num = 1;
            foreach ($section['questions'] as $q) {
                $rows[] = [
                    $section['label'],
                    $num,
                    $q['label'],
                    $q['avg'] ?? '—',
                    $q['count'],
                    $this->formatDistribution($q['dist'], $section['options']),
                ];
                $num++;
                $sheetRow++;
            }

            $rows[] = [$section['label'], '', 'Section Average', $section['avg'] ?? '—', '', ''];
            $this->boldRows[] = $sheetRow;
            $sheetRow++;
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [1 => ['font' => ['bold' => true]]];
        foreach ($this->boldRows as $row) {
            $styles[$row] = ['font' => ['bold' => true]];
        }

        return $styles;
    }
}
