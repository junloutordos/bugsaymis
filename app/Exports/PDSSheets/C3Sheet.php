<?php

namespace App\Exports\PDSSheets;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\BeforeSheet;

class C3Sheet implements WithEvents, WithTitle
{
    protected $pds;

    public function __construct($pds)
    {
        $this->pds = $pds;
    }

    public function title(): string
    {
        return 'C3';
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Voluntary Work
                $row = 5;
                foreach ($this->pds->voluntaryWork ?? [] as $work) { // <-- note camelCase and null-safe
                    $sheet->setCellValue('B'.$row, $vol->organization_name);
                    $sheet->setCellValue('C'.$row, $vol->from_date);
                    $sheet->setCellValue('D'.$row, $vol->to_date);
                    $sheet->setCellValue('E'.$row, $vol->position);
                    $sheet->setCellValue('F'.$row, $vol->hours);
                    $row++;
                }

                // Trainings/Seminars
                $row = 20;
                foreach ($this->pds->trainings ?? [] as $train) { // <-- corrected
                    $sheet->setCellValue('B'.$row, $train->training_title);
                    $sheet->setCellValue('C'.$row, $train->from_date);
                    $sheet->setCellValue('D'.$row, $train->to_date);
                    $sheet->setCellValue('E'.$row, $train->hours);
                    $sheet->setCellValue('F'.$row, $train->conducted_by);
                    $row++;
                }
            }
        ];
    }
}
