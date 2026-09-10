<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RandomStudentGroupsExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(private array $rows) {}

    public function headings(): array
    {
        return [
            'Group',
            'Name',
            'PISAY ID',
            'Grade & Section',
            'Sex',
        ];
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
