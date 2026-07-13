<?php

namespace App\Exports\AMS\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One row per activity, mirroring ActivityMonitorController::index()'s
 * dashboard rows plus a per-activity Avg Score column.
 */
class MonitorSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private array $rows) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function headings(): array
    {
        return [
            'Title', 'Type', 'Start Date', 'End Date', 'Venue', 'Creator', 'Status',
            'Participants', 'Attended', 'Evaluations', 'Completion Rate (%)', 'Avg Score',
        ];
    }

    public function array(): array
    {
        return array_map(fn ($r) => [
            $r['title'],
            $r['activity_type'] === 'training_workshop_seminar' ? 'Training/Workshop/Seminar' : 'In-house',
            $r['start_date'] ?? '—',
            $r['end_date'] ?? '—',
            $r['venue'] ?? '—',
            $r['creator'] ?? '—',
            ucfirst($r['status']),
            $r['participant_count'],
            $r['attended_count'],
            $r['evaluation_count'],
            $r['evaluation_completion_rate'] ?? '—',
            $r['avg_score'] ?? '—',
        ], $this->rows);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
