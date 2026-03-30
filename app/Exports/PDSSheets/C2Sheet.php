<?php

namespace App\Exports\PDSSheets;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\BeforeSheet;

class C2Sheet implements WithEvents, WithTitle
{
    protected $pds;

    public function __construct($pds)
    {
        $this->pds = $pds;
    }

    public function title(): string
    {
        return 'C2';
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Civil Service Eligibility
                $row = 5;
                foreach ($this->pds->eligibility as $cse) {
                    $sheet->setCellValue('B'.$row, $cse->career_service);
                    $sheet->setCellValue('C'.$row, $cse->rating);
                    $sheet->setCellValue('D'.$row, $cse->exam_date);
                    $sheet->setCellValue('E'.$row, $cse->exam_place);
                    $sheet->setCellValue('F'.$row, $cse->license_number);
                    $sheet->setCellValue('G'.$row, $cse->license_validity);
                    $row++;
                }

                // Work Experience
                // Work Experience (one-to-many)
                $row = 20;
                foreach ($this->pds->workExperience ?? [] as $work) { // <-- note camelCase and null-safe
                    $sheet->setCellValue('B'.$row, $work->position_title);
                    $sheet->setCellValue('C'.$row, $work->company_name);
                    $sheet->setCellValue('D'.$row, $work->from_date);
                    $sheet->setCellValue('E'.$row, $work->to_date);
                    $sheet->setCellValue('F'.$row, $work->salary);
                    $sheet->setCellValue('G'.$row, $work->status);
                    $sheet->setCellValue('H'.$row, $work->appointment_type);
                    $row++;
}

            }
        ];
    }
}
