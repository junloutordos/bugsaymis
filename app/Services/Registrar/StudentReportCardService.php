<?php

namespace App\Services\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentAnnualGrade;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use Mpdf\Mpdf;

class StudentReportCardService
{
    /**
     * Generate and return a PSHS-CRC Report Card PDF for one student.
     *
     * @return string  Raw PDF binary (stream it directly to the browser)
     */
    public function generate(int $studentId, int $schoolYearId): string
    {
        $student    = Student::findOrFail($studentId);
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        $enrollment = StudentEnrollment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->with('section.adviserUser:id,name')
            ->first();

        $section = $enrollment?->section;
        $adviser = $section?->adviserUser;

        $grades   = StudentAnnualGrade::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->whereNotNull('final_ge')
            ->orderBy('subject_name')
            ->get();

        $standing = StudentAcademicStanding::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->first();

        $html = $this->buildHtml($student, $schoolYear, $section, $adviser, $grades, $standing);

        $mpdf = new Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'orientation'       => 'P',
            'margin_left'       => 15,
            'margin_right'      => 15,
            'margin_top'        => 15,
            'margin_bottom'     => 15,
            'margin_footer'     => 5,
            'default_font'      => 'Arial',
            'default_font_size' => 9,
            'tempDir'           => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle("Report Card — {$student->full_name} ({$schoolYear->name})");
        $mpdf->SetAuthor('PSHS-CRC');
        $mpdf->SetHTMLFooter(
            '<div style="text-align:center;font-size:7pt;color:#888;">' .
            'Philippine Science High School — Caraga Region Campus · ' .
            'Generated: ' . now()->format('M d, Y h:i A') .
            '</div>'
        );

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    // ── HTML builder ──────────────────────────────────────────────────────────

    private function buildHtml(
        Student   $student,
        SchoolYear $schoolYear,
        ?Section  $section,
        ?User     $adviser,
        $grades,
        ?StudentAcademicStanding $standing,
    ): string {
        $gradeName    = $section ? "Grade {$section->levelid}" : '—';
        $sectionName  = $section?->sectionname ?? '—';
        $adviserName  = $adviser?->name ?? '—';
        $studentName  = strtoupper($student->full_name);
        $lrn          = $student->lrn ?? '—';
        $birthday     = $student->birthday ?? '—';
        $sex          = $student->sex === 'M' ? 'Male' : ($student->sex === 'F' ? 'Female' : '—');

        $gwaDisplay   = $standing?->gwa ? number_format((float) $standing->gwa, 3) : '—';
        $honors       = $standing?->honors ?? '—';
        $standingText = $standing?->standing ?? '—';

        // Grade rows HTML
        $gradeRows = '';
        foreach ($grades as $g) {
            $q1     = $g->q1_ge    !== null ? number_format((float) $g->q1_ge, 3)    : '—';
            $q2     = $g->q2_ge    !== null ? number_format((float) $g->q2_ge, 3)    : '—';
            $q3     = $g->q3_ge    !== null ? number_format((float) $g->q3_ge, 3)    : '—';
            $q4     = $g->q4_ge    !== null ? number_format((float) $g->q4_ge, 3)    : '—';
            $final  = $g->final_ge !== null ? number_format((float) $g->final_ge, 3) : '—';
            $remark = $g->isPassed() ? 'Passed' : 'Failed';
            $remarkStyle = $g->isPassed() ? 'color:#166534' : 'color:#991b1b;font-weight:bold';

            $gradeRows .= "
            <tr>
                <td style='text-align:left;padding:3px 6px;'>{$g->subject_name}</td>
                <td style='text-align:center;padding:3px 4px;'>{$q1}</td>
                <td style='text-align:center;padding:3px 4px;'>{$q2}</td>
                <td style='text-align:center;padding:3px 4px;'>{$q3}</td>
                <td style='text-align:center;padding:3px 4px;'>{$q4}</td>
                <td style='text-align:center;padding:3px 4px;font-weight:bold;'>{$final}</td>
                <td style='text-align:center;padding:3px 4px;{$remarkStyle}'>{$remark}</td>
            </tr>";
        }

        if ($gradeRows === '') {
            $gradeRows = "<tr><td colspan='7' style='text-align:center;color:#999;padding:8px;'>No grades computed yet.</td></tr>";
        }

        $honorsNote = ($honors && $honors !== 'None' && $honors !== '—')
            ? "<p style='text-align:center;color:#92400e;font-weight:bold;margin:4px 0;'>{$honors}</p>"
            : '';

        return "
        <html><body style='font-family:Arial,sans-serif;font-size:9pt;color:#1e293b;'>

        <!-- Header -->
        <table width='100%' style='margin-bottom:10px;'>
            <tr>
                <td width='15%' style='text-align:center;vertical-align:middle;'>
                    <img src='" . public_path('images/pshs_logo.png') . "' height='60' alt='PSHS' />
                </td>
                <td style='text-align:center;vertical-align:middle;'>
                    <p style='margin:0;font-size:8pt;color:#475569;'>Republic of the Philippines</p>
                    <p style='margin:1px 0;font-weight:bold;font-size:10pt;'>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</p>
                    <p style='margin:0;font-size:9pt;font-weight:bold;'>CARAGA REGION CAMPUS</p>
                    <p style='margin:2px 0 0;font-size:8pt;color:#64748b;'>Ampayon, Butuan City, Agusan del Norte</p>
                    <p style='margin:4px 0 0;font-size:11pt;font-weight:bold;letter-spacing:1px;border-top:1px solid #cbd5e1;padding-top:4px;'>REPORT CARD</p>
                    <p style='margin:0;font-size:9pt;'>School Year {$schoolYear->name}</p>
                </td>
                <td width='15%'></td>
            </tr>
        </table>

        <!-- Student Info -->
        <table width='100%' style='border-collapse:collapse;margin-bottom:8px;font-size:9pt;'>
            <tr>
                <td width='50%' style='padding:2px 0;'><b>Student Name:</b> {$studentName}</td>
                <td width='25%' style='padding:2px 0;'><b>Grade:</b> {$gradeName}</td>
                <td width='25%' style='padding:2px 0;'><b>Section:</b> {$sectionName}</td>
            </tr>
            <tr>
                <td style='padding:2px 0;'><b>LRN:</b> {$lrn}</td>
                <td style='padding:2px 0;'><b>Date of Birth:</b> {$birthday}</td>
                <td style='padding:2px 0;'><b>Sex:</b> {$sex}</td>
            </tr>
            <tr>
                <td colspan='2' style='padding:2px 0;'><b>Adviser:</b> {$adviserName}</td>
                <td style='padding:2px 0;'></td>
            </tr>
        </table>

        <!-- Grades Table -->
        <table width='100%' style='border-collapse:collapse;font-size:9pt;'>
            <thead>
                <tr style='background:#1e3a5f;color:white;'>
                    <th style='text-align:left;padding:5px 6px;width:40%;'>Subject</th>
                    <th style='text-align:center;padding:5px 4px;width:10%;'>Q1</th>
                    <th style='text-align:center;padding:5px 4px;width:10%;'>Q2</th>
                    <th style='text-align:center;padding:5px 4px;width:10%;'>Q3</th>
                    <th style='text-align:center;padding:5px 4px;width:10%;'>Q4</th>
                    <th style='text-align:center;padding:5px 4px;width:10%;'>Final</th>
                    <th style='text-align:center;padding:5px 4px;width:10%;'>Remarks</th>
                </tr>
            </thead>
            <tbody style='border:1px solid #cbd5e1;'>
                {$gradeRows}
            </tbody>
        </table>

        <!-- Summary -->
        <table width='100%' style='margin-top:10px;font-size:9pt;'>
            <tr>
                <td width='60%'></td>
                <td width='40%' style='border:1px solid #cbd5e1;padding:6px 10px;'>
                    <table width='100%'>
                        <tr>
                            <td><b>General Weighted Average:</b></td>
                            <td style='text-align:right;font-weight:bold;font-size:10pt;'>{$gwaDisplay}</td>
                        </tr>
                        <tr>
                            <td><b>Academic Standing:</b></td>
                            <td style='text-align:right;'>{$standingText}</td>
                        </tr>
                    </table>
                    {$honorsNote}
                </td>
            </tr>
        </table>

        <!-- Signatures -->
        <table width='100%' style='margin-top:30px;font-size:9pt;'>
            <tr>
                <td width='33%' style='text-align:center;padding:0 10px;'>
                    <p style='border-top:1px solid #334155;margin-top:30px;padding-top:4px;'>{$adviserName}</p>
                    <p style='margin:0;font-size:8pt;color:#64748b;'>Class Adviser</p>
                </td>
                <td width='33%' style='text-align:center;padding:0 10px;'>
                    <p style='border-top:1px solid #334155;margin-top:30px;padding-top:4px;'>&nbsp;</p>
                    <p style='margin:0;font-size:8pt;color:#64748b;'>Registrar</p>
                </td>
                <td width='33%' style='text-align:center;padding:0 10px;'>
                    <p style='border-top:1px solid #334155;margin-top:30px;padding-top:4px;'>&nbsp;</p>
                    <p style='margin:0;font-size:8pt;color:#64748b;'>Campus Director</p>
                </td>
            </tr>
        </table>

        <!-- Note on GE scale -->
        <p style='margin-top:12px;font-size:7pt;color:#94a3b8;'>
            Grade Equivalents: 1.0 = Outstanding · 1.5 = Very Satisfactory · 2.0 = Satisfactory ·
            2.5 = Fairly Satisfactory · 3.0 = Did Not Meet Expectations · 5.0 = Failed
        </p>

        </body></html>";
    }
}
