<?php

namespace App\Exports\AMS\Sheets;

use App\Exports\AMS\Concerns\FormatsLikertOptions;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One row per respondent. $fieldLabels maps a raw column name (e.g. "obj_1")
 * to its question text, in display order. Set $withActivityColumns to prepend
 * "Activity" / "Activity Type" columns for a bulk, multi-activity export.
 */
class RawFeedbackSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    use FormatsLikertOptions;

    public function __construct(
        private Collection $rows,
        private array $fieldLabels,
        private bool $isTws = false,
        private bool $withActivityColumn = false,
        private string $sheetTitle = 'Raw Feedback',
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        $headings = [];
        if ($this->withActivityColumn) {
            $headings[] = 'Activity';
        }
        $headings[] = 'Respondent Type';
        $headings[] = 'Respondent Name';
        $headings[] = 'Submitted At';
        if ($this->isTws) {
            $headings[] = 'Position/Function';
        }
        foreach ($this->fieldLabels as $label) {
            $headings[] = $label;
        }
        $headings[] = 'Suggestions';
        $headings[] = 'Other Comments';

        return $headings;
    }

    public function array(): array
    {
        return $this->rows->map(function ($r) {
            $row = [];
            if ($this->withActivityColumn) {
                $row[] = $r->activity?->title ?? "Activity #{$r->activity_id}";
            }
            $row[] = ucfirst($r->participant_type);
            $row[] = $r->evaluator_name ?? 'Anonymous';
            $row[] = $r->created_at?->format('Y-m-d H:i');
            if ($this->isTws) {
                $row[] = $r->position_function;
            }
            foreach (array_keys($this->fieldLabels) as $field) {
                $row[] = $this->formatOption($r->$field);
            }
            $row[] = $r->suggestions;
            $row[] = $r->other_comments;

            return $row;
        })->values()->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
