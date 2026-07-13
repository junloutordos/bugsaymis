<?php

namespace App\Exports\AMS\Sheets;

use App\Exports\AMS\Concerns\FormatsLikertOptions;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Aggregate-only per-speaker results, from
 * ActivityEvaluationSummaryService::build()['speakers'] (TWS activities only).
 * A bold "Speaker: Name — Topic" row separates each speaker's block.
 */
class SpeakerResultsSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    use FormatsLikertOptions;

    private array $boldRows = [];

    public function __construct(private array $speakerSummaries, private string $sheetTitle = 'Speaker Evaluations') {}

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
        $sheetRow = 2;

        foreach ($this->speakerSummaries as $summary) {
            $speaker = $summary['speaker'];
            $label   = 'Speaker: ' . $speaker['name'] . ($speaker['topic'] ? ' — ' . $speaker['topic'] : '');

            $rows[] = [$label, '', '', '', '', ''];
            $this->boldRows[] = $sheetRow;
            $sheetRow++;

            foreach ($summary['sections'] as $section) {
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
