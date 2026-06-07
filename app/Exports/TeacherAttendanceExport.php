<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TeacherAttendanceExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(private array $rows) {}

    public function headings(): array
    {
        return [
            'Teacher',
            'Classroom',
            'Subject',
            'Section',
            'Tapped At',
            'Status',
            'Late (min)',
        ];
    }

    public function array(): array
    {
        return array_map(function ($log) {
            return [
                $log->teacher?->name ?? '—',
                $log->classroom?->name ?? '—',
                $log->classSchedule?->subject?->name ?? '—',
                $log->classSchedule?->section?->name ?? '—',
                $log->tapped_at?->format('Y-m-d H:i') ?? '—',
                ucfirst(str_replace('_', ' ', $log->status)),
                $log->late_minutes ?? 0,
            ];
        }, $this->rows);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
