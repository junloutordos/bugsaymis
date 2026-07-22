<?php

namespace Tests\Unit;

use App\Services\PdsTrainingContinuationService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PdsTrainingContinuationServiceTest extends TestCase
{
    public function test_training_overflow_is_split_into_formatted_continuation_sheets(): void
    {
        $expectations = [
            21 => 0,
            22 => 1,
            42 => 1,
            43 => 2,
        ];

        foreach ($expectations as $trainingCount => $continuationCount) {
            $spreadsheet = $this->loadTemplate();
            $trainings = $this->trainings($trainingCount);

            $added = (new PdsTrainingContinuationService)->populate(
                $spreadsheet,
                $trainings,
                'Juan & Maria Dela Cruz',
            );

            $this->assertCount($continuationCount, $added, "Unexpected continuation count for {$trainingCount} trainings.");
            $this->assertSame('Training 1', $spreadsheet->getSheetByName('C3')->getCell('A18')->getValue());
            $this->assertSame('Training 21', $spreadsheet->getSheetByName('C3')->getCell('A38')->getValue());
            $this->assertStringContainsString('Continue on separate sheet', $spreadsheet->getSheetByName('C3')->getCell('A39')->getValue());

            if ($trainingCount >= 22) {
                $continuation = $spreadsheet->getSheetByName('C3-LD Continuation 2');
                $this->assertNotNull($continuation);
                $this->assertSame('Training 22', $continuation->getCell('A18')->getValue());
                $this->assertSame('A14:K39', $continuation->getPageSetup()->getPrintArea());
                $this->assertStringContainsString('Juan && Maria Dela Cruz', $continuation->getHeaderFooter()->getOddHeader());
                $this->assertFalse($continuation->getRowDimension(13)->getVisible());
                $this->assertFalse($continuation->getRowDimension(40)->getVisible());
            }

            if ($trainingCount === 42) {
                $this->assertSame('Training 42', $spreadsheet->getSheetByName('C3-LD Continuation 2')->getCell('A38')->getValue());
            }

            if ($trainingCount === 43) {
                $this->assertSame('Training 43', $spreadsheet->getSheetByName('C3-LD Continuation 3')->getCell('A18')->getValue());
            }

            $spreadsheet->disconnectWorksheets();
        }
    }

    public function test_continuation_sheets_survive_xlsx_serialization_without_touching_other_information(): void
    {
        $spreadsheet = $this->loadTemplate();
        (new PdsTrainingContinuationService)->populate($spreadsheet, $this->trainings(43), 'Juan Dela Cruz');

        $tempFile = tempnam(sys_get_temp_dir(), 'pds_training_overflow_');

        try {
            (new Xlsx($spreadsheet))->save($tempFile);
            $reloaded = IOFactory::load($tempFile);

            $this->assertSame(
                ['C1', 'C2', 'C3', 'C3-LD Continuation 2', 'C3-LD Continuation 3', 'C4', 'Lookup'],
                $reloaded->getSheetNames(),
            );
            $this->assertSame('Training 43', $reloaded->getSheetByName('C3-LD Continuation 3')->getCell('A18')->getValue());
            $this->assertStringContainsString('VIII.', $reloaded->getSheetByName('C3')->getCell('A40')->getValue());
            $this->assertNull($reloaded->getSheetByName('C3')->getCell('A42')->getValue());

            $reloaded->disconnectWorksheets();
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($tempFile);
        }
    }

    private function loadTemplate(): Spreadsheet
    {
        return IOFactory::load(base_path('templates/pds_template2025.xlsx'));
    }

    /** @return array<int, object> */
    private function trainings(int $count): array
    {
        return collect(range(1, $count))->map(fn (int $number) => (object) [
            'training_title' => "Training {$number}",
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-02',
            'hours' => 8,
            'training_type' => 'Technical',
            'conducted_by' => 'PSHS-CRC',
        ])->all();
    }
}
