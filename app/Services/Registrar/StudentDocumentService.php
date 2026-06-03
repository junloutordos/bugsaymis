<?php

namespace App\Services\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use Mpdf\Mpdf;

class StudentDocumentService
{
    private const CAMPUS = 'Philippine Science High School — Caraga Region Campus';
    private const ADDRESS = 'Ampayon, Butuan City, Agusan del Norte';
    private const HEADER_COLOR = '#1e3a5f';

    public function generateCertificateOfEnrollment(int $studentId, int $schoolYearId): string
    {
        $student    = Student::findOrFail($studentId);
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        $enrollment = StudentEnrollment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->with('section:id,sectionname,levelid,adviser')
            ->firstOrFail();

        $grade       = "Grade {$enrollment->grade_level}";
        $sectionName = $enrollment->section?->sectionname ?? '—';
        $today       = now()->format('F d, Y');

        $html = $this->wrap("Certificate of Enrollment", "
        <p>This is to certify that</p>
        <p style='font-size:16pt;font-weight:bold;text-align:center;margin:16px 0;text-transform:uppercase;'>
            {$student->full_name}
        </p>
        <p>
            with Learner Reference Number (LRN) <b>{$student->lrn}</b>,
            is officially enrolled at the <b>" . self::CAMPUS . "</b>
            for School Year <b>{$schoolYear->name}</b>,
            in <b>{$grade}</b>, Section <b>{$sectionName}</b>.
        </p>
        <p>This certification is issued upon the request of the above-named student for <b>whatever legal purpose it may serve</b>.</p>
        <p>Issued this <b>{$today}</b> at Butuan City, Agusan del Norte, Philippines.</p>
        ");

        return $this->buildPdf($html, "COE_{$student->full_name}_{$schoolYear->name}");
    }

    public function generateCertificateOfGoodMoral(int $studentId, int $schoolYearId): string
    {
        $student    = Student::findOrFail($studentId);
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        $enrollment = StudentEnrollment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->with('section:id,sectionname,levelid')
            ->firstOrFail();

        $grade       = "Grade {$enrollment->grade_level}";
        $sectionName = $enrollment->section?->sectionname ?? '—';
        $today       = now()->format('F d, Y');

        $html = $this->wrap("Certificate of Good Moral Character", "
        <p>This is to certify that</p>
        <p style='font-size:16pt;font-weight:bold;text-align:center;margin:16px 0;text-transform:uppercase;'>
            {$student->full_name}
        </p>
        <p>
            a student of the <b>" . self::CAMPUS . "</b>, currently enrolled in <b>{$grade}</b>,
            Section <b>{$sectionName}</b> for School Year <b>{$schoolYear->name}</b>,
            is known to be of <b>good moral character</b>. The student has not been
            subject to any disciplinary action and has maintained a satisfactory
            conduct record while at this institution.
        </p>
        <p>This certification is issued upon the request of the above-named student for <b>whatever legal purpose it may serve</b>.</p>
        <p>Issued this <b>{$today}</b> at Butuan City, Agusan del Norte, Philippines.</p>
        ");

        return $this->buildPdf($html, "GoodMoral_{$student->full_name}_{$schoolYear->name}");
    }

    public function generateClearance(int $studentId, int $schoolYearId): string
    {
        $student    = Student::findOrFail($studentId);
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        $enrollment = StudentEnrollment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->with('section:id,sectionname,levelid')
            ->firstOrFail();

        $grade       = "Grade {$enrollment->grade_level}";
        $sectionName = $enrollment->section?->sectionname ?? '—';
        $today       = now()->format('F d, Y');

        // Clearance offices
        $offices = [
            ['Registrar\'s Office',    'Check enrollment records and clearance of obligations'],
            ['Library',                'No unreturned books/materials'],
            ['Finance Office',         'No outstanding monetary obligations'],
            ['Health / Clinic',        'Complete health records on file'],
            ['Dormitory',              'No dormitory violations or damages (if applicable)'],
            ['Student Affairs Office', 'No pending disciplinary cases'],
            ['Campus Director\'s Office', 'Final approval'],
        ];

        $officeRows = '';
        foreach ($offices as $i => [$name, $desc]) {
            $bg = $i % 2 === 0 ? '#f8fafc' : '#ffffff';
            $officeRows .= "
            <tr style='background:{$bg};'>
                <td style='padding:7px 8px;border:1px solid #e2e8f0;'>{$name}</td>
                <td style='padding:7px 8px;border:1px solid #e2e8f0;font-size:8pt;color:#64748b;'>{$desc}</td>
                <td style='padding:7px 8px;border:1px solid #e2e8f0;width:120px;text-align:center;'>&nbsp;</td>
                <td style='padding:7px 8px;border:1px solid #e2e8f0;width:100px;text-align:center;'>&nbsp;</td>
            </tr>";
        }

        $html = $this->wrap("Student Clearance", "
        <table style='width:100%;margin-bottom:14px;font-size:9pt;'>
            <tr>
                <td><b>Student Name:</b> " . strtoupper($student->full_name) . "</td>
                <td><b>LRN:</b> {$student->lrn}</td>
            </tr>
            <tr>
                <td><b>Grade & Section:</b> {$grade} — {$sectionName}</td>
                <td><b>School Year:</b> {$schoolYear->name}</td>
            </tr>
        </table>

        <table style='width:100%;border-collapse:collapse;font-size:9pt;'>
            <thead>
                <tr style='background:" . self::HEADER_COLOR . ";color:white;'>
                    <th style='padding:6px 8px;text-align:left;'>Office / Department</th>
                    <th style='padding:6px 8px;text-align:left;'>Remarks</th>
                    <th style='padding:6px 8px;text-align:center;width:120px;'>Cleared By</th>
                    <th style='padding:6px 8px;text-align:center;width:100px;'>Date</th>
                </tr>
            </thead>
            <tbody>{$officeRows}</tbody>
        </table>

        <p style='margin-top:20px;font-size:8pt;color:#64748b;'>
            This form must be completed and signed by the designated representative of each office listed above.
            Issued on <b>{$today}</b>.
        </p>
        ");

        return $this->buildPdf($html, "Clearance_{$student->full_name}_{$schoolYear->name}");
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function wrap(string $title, string $body): string
    {
        $campus  = self::CAMPUS;
        $address = self::ADDRESS;
        $color   = self::HEADER_COLOR;
        $logoPath = public_path('images/pshs_logo.png');

        return "
        <html><body style='font-family:Arial,sans-serif;font-size:10pt;color:#1e293b;'>
        <table width='100%' style='margin-bottom:16px;'>
            <tr>
                <td width='12%' style='text-align:center;vertical-align:middle;'>
                    <img src='{$logoPath}' height='55' alt='PSHS' />
                </td>
                <td style='text-align:center;vertical-align:middle;'>
                    <p style='margin:0;font-size:7.5pt;color:#64748b;'>Republic of the Philippines</p>
                    <p style='margin:1px 0;font-weight:bold;font-size:10pt;'>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</p>
                    <p style='margin:0;font-size:9pt;font-weight:bold;'>{$campus}</p>
                    <p style='margin:2px 0 0;font-size:8pt;color:#64748b;'>{$address}</p>
                </td>
            </tr>
        </table>

        <h2 style='text-align:center;font-size:13pt;letter-spacing:1px;
                   border-bottom:2px solid {$color};padding-bottom:6px;margin-bottom:16px;
                   color:{$color};text-transform:uppercase;'>
            {$title}
        </h2>

        <div style='line-height:1.8;'>
            {$body}
        </div>

        <div style='margin-top:40px;'>
            <table width='100%' style='font-size:9pt;'>
                <tr>
                    <td width='40%' style='text-align:center;'>
                        <p style='border-top:1px solid #334155;margin-top:30px;padding-top:4px;'>&nbsp;</p>
                        <p style='margin:0;font-size:8pt;color:#64748b;'>Registrar</p>
                    </td>
                    <td width='20%'></td>
                    <td width='40%' style='text-align:center;'>
                        <p style='border-top:1px solid #334155;margin-top:30px;padding-top:4px;'>&nbsp;</p>
                        <p style='margin:0;font-size:8pt;color:#64748b;'>Campus Director</p>
                    </td>
                </tr>
            </table>
        </div>
        </body></html>";
    }

    private function buildPdf(string $html, string $titleBase): string
    {
        $mpdf = new Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'orientation'       => 'P',
            'margin_left'       => 25,
            'margin_right'      => 25,
            'margin_top'        => 20,
            'margin_bottom'     => 20,
            'default_font'      => 'Arial',
            'default_font_size' => 10,
            'tempDir'           => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle($titleBase);
        $mpdf->SetAuthor('PSHS-CRC');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
