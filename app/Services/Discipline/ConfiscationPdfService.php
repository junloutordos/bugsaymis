<?php

namespace App\Services\Discipline;

use App\Models\Discipline\DisciplineConfiscatedItem;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates the Confiscation Receipt and Item Claim Form PDFs.
 * A single A4 document holds both forms (receipt on top, claim form below).
 */
class ConfiscationPdfService
{
    public function __construct(private StudentResolver $students) {}

    public function stream(DisciplineConfiscatedItem $item): StreamedResponse
    {
        $pdfBytes = $this->buildPdfBytes($item);
        $filename = 'Confiscation_' . $item->receipt_no . '.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($pdfBytes),
        ]);
    }

    private function buildPdfBytes(DisciplineConfiscatedItem $item): string
    {
        $item->loadMissing(['confiscatedBy', 'claimAckBy']);
        $student = $this->students->resolve($item->student_id);

        $html = view('discipline.confiscation-form', [
            'item'    => $item,
            'student' => $student,
        ])->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Confiscation — ' . $item->receipt_no);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
