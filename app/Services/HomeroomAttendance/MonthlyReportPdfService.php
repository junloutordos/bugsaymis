<?php

namespace App\Services\HomeroomAttendance;

use App\Models\FacultyLoading\Section;
use App\Models\HomeroomAttendance\MonthlyReport;
use App\Models\User;
use App\Services\PersonNameFormatter;
use Carbon\Carbon;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyReportPdfService
{
    public function __construct(private PersonNameFormatter $nameFormatter)
    {
    }

    public function render(MonthlyReport $report, Section $section, ?User $adviser, ?User $coordinator): StreamedResponse
    {
        $report->loadMissing('lines.student');

        $boys = $report->lines->filter(fn ($line) => strtoupper((string) $line->student?->sex) === 'M')->values();
        $girls = $report->lines->filter(fn ($line) => strtoupper((string) $line->student?->sex) !== 'M')->values();

        $html = view('homeroom-attendance.monthly-report-pdf', [
            'section'          => $section,
            'report'           => $report,
            'boys'             => $boys,
            'girls'            => $girls,
            'monthLabel'       => Carbon::create($report->year, $report->month, 1)->format('F Y'),
            'adviserName'      => $adviser ? $this->nameFormatter->formal($adviser) : null,
            'coordinatorName'  => $coordinator ? $this->nameFormatter->formal($coordinator) : null,
        ])->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Record on Attendance and Punctuality — '.$section->sectionname);
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');
        $filename = 'Attendance-Punctuality_'.$section->sectionname.'_'.$report->year.'-'.str_pad((string) $report->month, 2, '0', STR_PAD_LEFT).'.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length'      => strlen($pdfBytes),
        ]);
    }
}
