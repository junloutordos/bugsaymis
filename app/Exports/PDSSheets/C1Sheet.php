<?php

namespace App\Exports\PDSSheets;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\BeforeSheet;

class C1Sheet implements WithEvents, WithCustomStartCell, WithTitle
{
    protected $pds;

    public function __construct($pds)
    {
        $this->pds = $pds;
    }

    public function title(): string
    {
        return 'C1';
    }

    public function startCell(): string
    {
        return 'B5';
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Personal Info
                $sheet->setCellValue('D10', $this->pds->surname);
                $sheet->setCellValue('D11', $this->pds->first_name);
                $sheet->setCellValue('D12', $this->pds->middle_name);
                $sheet->setCellValue('D13', $this->pds->birth_date);
                $sheet->setCellValue('F5', $this->pds->sex);
                $sheet->setCellValue('G5', $this->pds->civil_status);
                $sheet->setCellValue('H5', $this->pds->citizenship);

                // Family Background
                $sheet->setCellValue('B15', $this->pds->family_background->father_surname ?? '');
                $sheet->setCellValue('C15', $this->pds->family_background->father_first_name ?? '');
                $sheet->setCellValue('B16', $this->pds->family_background->mother_surname ?? '');
                $sheet->setCellValue('C16', $this->pds->family_background->mother_first_name ?? '');

                // Educational Background
                $row = 25;
                foreach ($this->pds->education as $edu) {
                    $sheet->setCellValue('B'.$row, $edu->level);
                    $sheet->setCellValue('C'.$row, $edu->school_name);
                    $sheet->setCellValue('D'.$row, $edu->degree_course);
                    $sheet->setCellValue('E'.$row, $edu->year_graduated);
                    $sheet->setCellValue('F'.$row, $edu->highest_level);
                    $sheet->setCellValue('G'.$row, $edu->scholarship);
                    $row++;
                }
            }
        ];
    }
}
