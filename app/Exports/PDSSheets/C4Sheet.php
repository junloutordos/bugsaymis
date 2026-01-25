<?php

namespace App\Exports\PDSSheets;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\BeforeSheet;

class C4Sheet implements WithEvents, WithTitle
{
    protected $pds;

    public function __construct($pds)
    {
        $this->pds = $pds;
    }

    public function title(): string
    {
        return 'C4';
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // You can add more sections: Recognitions, Skills, Memberships
                $sheet->setCellValue('B5', $this->pds->skills ?? '');
                $sheet->setCellValue('B6', $this->pds->recognitions ?? '');
                $sheet->setCellValue('B7', $this->pds->membership ?? '');
            }
        ];
    }
}
