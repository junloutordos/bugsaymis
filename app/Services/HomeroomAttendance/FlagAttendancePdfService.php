<?php

namespace App\Services\HomeroomAttendance;

use App\Models\FacultyLoading\Section;
use App\Models\HomeroomAttendance\FlagAttendanceDate;
use App\Models\User;
use App\Services\PersonNameFormatter;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FlagAttendancePdfService
{
    public function __construct(private PersonNameFormatter $nameFormatter)
    {
    }

    public function render(FlagAttendanceDate $attendanceDate, Section $section, ?User $adviser): StreamedResponse
    {
        $attendanceDate->loadMissing('records.student');

        $eventLabel = $attendanceDate->event_type === 'flag_retreat' ? 'Flag Retreat' : 'Flag Ceremony';

        $students = $attendanceDate->records
            ->sortBy(fn ($r) => [$r->student?->lastname, $r->student?->firstname])
            ->values();

        $html = view('homeroom-attendance.flag-attendance-pdf', [
            'section'     => $section,
            'attendance'  => $attendanceDate,
            'eventLabel'  => $eventLabel,
            'students'    => $students,
            'adviserName' => $adviser ? $this->nameFormatter->formal($adviser) : null,
        ])->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle($eventLabel.' Attendance — '.$section->sectionname);
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');
        $filename = str_replace(' ', '-', $eventLabel).'-Attendance_'.$section->sectionname.'_'.$attendanceDate->date->toDateString().'.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length'      => strlen($pdfBytes),
        ]);
    }
}
