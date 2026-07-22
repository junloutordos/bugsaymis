<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PdsTrainingContinuationService
{
    public const ROWS_PER_SHEET = 21;

    /**
     * Populate C3 and create a formatted continuation worksheet for every
     * additional group of training records.
     *
     * @return array<int, string> Names of continuation worksheets that were added.
     */
    public function populate(Spreadsheet $spreadsheet, iterable $trainings, string $employeeName): array
    {
        $primary = $spreadsheet->getSheetByName('C3');

        if (! $primary) {
            return [];
        }

        $records = collect($trainings)->values();
        $chunks = $records->chunk(self::ROWS_PER_SHEET)->values();
        $continuationTemplate = clone $primary;

        $this->writeTrainings($primary, $chunks->first() ?? collect());

        if ($chunks->count() <= 1) {
            return [];
        }

        $primaryIndex = $spreadsheet->getIndex($primary);
        $sheetCount = $chunks->count();
        $added = [];

        foreach ($chunks->slice(1)->values() as $chunkIndex => $chunk) {
            $pageNumber = $chunkIndex + 2;
            $sheet = clone $continuationTemplate;
            $sheetName = "C3-LD Continuation {$pageNumber}";

            $sheet->setTitle($sheetName);
            $this->configureContinuationSheet($sheet, $employeeName, $pageNumber, $sheetCount);
            $this->writeTrainings($sheet, $chunk);

            $spreadsheet->addSheet($sheet, $primaryIndex + $pageNumber - 1);
            $added[] = $sheetName;
        }

        return $added;
    }

    private function configureContinuationSheet(
        Worksheet $sheet,
        string $employeeName,
        int $pageNumber,
        int $sheetCount,
    ): void {
        $safeEmployeeName = str_replace('&', '&&', trim($employeeName));

        $sheet->getPageSetup()
            ->setPrintArea('A14:K39')
            ->setFitToWidth(1)
            ->setFitToHeight(1);

        $sheet->getHeaderFooter()->setOddHeader(
            "&L&BEmployee: {$safeEmployeeName}&R&BTraining Continuation {$pageNumber} of {$sheetCount}"
        );
        $sheet->getHeaderFooter()->setOddFooter('&LCS Form No. 212 — C3&RPage &P');

        for ($row = 1; $row <= 13; $row++) {
            $sheet->getRowDimension($row)->setVisible(false);
        }
        for ($row = 40; $row <= 52; $row++) {
            $sheet->getRowDimension($row)->setVisible(false);
        }
    }

    private function writeTrainings(Worksheet $sheet, Collection $trainings): void
    {
        $row = 18;

        foreach ($trainings as $training) {
            $sheet->setCellValue("A{$row}", data_get($training, 'training_title', ''));
            $sheet->setCellValue("E{$row}", $this->formatDate(data_get($training, 'date_from')));
            $sheet->setCellValue("F{$row}", $this->formatDate(data_get($training, 'date_to')));
            $sheet->setCellValue("G{$row}", data_get($training, 'hours', ''));
            $sheet->setCellValue("H{$row}", data_get($training, 'training_type', ''));
            $sheet->setCellValue("I{$row}", data_get($training, 'conducted_by', ''));
            $row++;
        }
    }

    private function formatDate(mixed $value): string
    {
        return filled($value) ? Carbon::parse($value)->format('m-d-Y') : '';
    }
}
