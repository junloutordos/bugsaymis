<?php

namespace App\Services\StudentClearance;

use App\Models\ResidenceHall\RhIntern;
use App\Models\Student;
use App\Models\StudentClearance\StudentClearance;
use App\Models\StudentClearance\StudentClearanceItem;
use Mpdf\Mpdf;

class StudentClearancePdfService
{
    public function generate(StudentClearance $clearance): string
    {
        $clearance->loadMissing([
            'period.schoolYear:id,name,is_current',
            'section:id,sectionname,levelid',
            'adviser:id,name',
            'items.assignedUser:id,name',
            'items.signer:id,name',
        ]);

        $student = Student::findOrFail($clearance->student_id);
        $html = $this->html($clearance, $student);

        $mpdf = new Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'orientation'       => 'L',
            'margin_left'       => 8,
            'margin_right'      => 8,
            'margin_top'        => 7,
            'margin_bottom'     => 7,
            'default_font'      => 'Arial',
            'default_font_size' => 8,
            'tempDir'           => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Year-End Clearance - '.$student->full_name);
        $mpdf->SetAuthor('PSHS-CRC');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function html(StudentClearance $clearance, Student $student): string
    {
        $academicItems = $clearance->items
            ->whereIn('requirement_group', ['subject', 'laboratory'])
            ->values();
        $adminItems = $clearance->items
            ->whereIn('requirement_group', ['administrative', 'final'])
            ->values();

        $academicRows = $this->rows($academicItems, 16);
        $adminRows = $this->rows($adminItems, 18);
        $logoPath = public_path('images/pshs_logo.png');
        $residenceStatus = RhIntern::where('student_id', $student->id)
            ->where('school_year_id', $clearance->school_year_id)
            ->whereIn('status', ['active', 'checked_in'])
            ->exists() ? 'Intern' : 'Extern';

        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #111827; font-size: 8pt; }
                table { border-collapse: collapse; width: 100%; }
                .bordered td, .bordered th { border: 0.6pt solid #111827; padding: 3px 4px; vertical-align: top; }
                .center { text-align: center; }
                .label { font-weight: bold; }
                .muted { color: #475569; }
                .title { font-size: 13pt; font-weight: bold; letter-spacing: 0.5px; }
                .small { font-size: 7pt; }
                .sign-line { border-top: 0.6pt solid #111827; text-align: center; padding-top: 3px; }
            </style>
        </head>
        <body>
            <table>
                <tr>
                    <td style='width:12%; text-align:center;'>
                        <img src='{$logoPath}' style='height:48px;' />
                    </td>
                    <td class='center'>
                        <div class='small'>Republic of the Philippines</div>
                        <div style='font-weight:bold;'>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</div>
                        <div>Caraga Region Campus</div>
                        <div class='small muted'>Ampayon, Butuan City, Agusan del Norte</div>
                    </td>
                    <td style='width:24%;'>
                        <table class='bordered small'>
                            <tr><td class='label'>Form No.</td><td>PSHS-00-F-REG-05</td></tr>
                            <tr><td class='label'>Version/Rev.</td><td>Ver02 / Rev0</td></tr>
                            <tr><td class='label'>Status</td><td>".$this->h($this->statusLabel($clearance->status))."</td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div class='center title' style='margin: 6px 0 8px;'>YEAR-END CLEARANCE</div>

            <table class='bordered' style='margin-bottom: 7px;'>
                <tr>
                    <td style='width:12%;' class='label'>Name</td>
                    <td style='width:38%;'>".$this->h($student->full_name)."</td>
                    <td style='width:14%;' class='label'>Year & Section</td>
                    <td style='width:18%;'>Grade ".$this->h((string) $clearance->grade_level).' '.$this->h($clearance->section?->sectionname ?? '')."</td>
                    <td style='width:10%;' class='label'>School Year</td>
                    <td>".$this->h($clearance->period?->schoolYear?->name ?? '')."</td>
                </tr>
                <tr>
                    <td class='label'>Home Address</td>
                    <td>".$this->h($student->address ?? $student->home_address ?? '')."</td>
                    <td class='label'>Tel. No.</td>
                    <td>".$this->h($student->contactno ?? $student->contact_number ?? '')."</td>
                    <td class='label'>Scholarship Category</td>
                    <td>".$this->h($student->scholarship_category ?? $student->category ?? '')."</td>
                </tr>
                <tr>
                    <td class='label'>Residence Status</td>
                    <td>".$this->h($residenceStatus)."</td>
                    <td class='label'>PISAY ID</td>
                    <td>".$this->h($student->pisaysystemID ?? $clearance->pisaysystem_id)."</td>
                    <td class='label'>Date Finalized</td>
                    <td>".$this->h($clearance->finalized_at?->format('F d, Y') ?? '')."</td>
                </tr>
            </table>

            <table style='width:100%;'>
                <tr>
                    <td style='width:50%; padding-right:4px;'>
                        <table class='bordered'>
                            <thead>
                                <tr><th colspan='4' class='center'>ACADEMIC / SUBJECT CLEARANCE</th></tr>
                                <tr>
                                    <th style='width:39%;'>Subject / Office</th>
                                    <th style='width:13%;'>Status</th>
                                    <th style='width:31%;'>Signature / Name</th>
                                    <th style='width:17%;'>Date</th>
                                </tr>
                            </thead>
                            <tbody>{$academicRows}</tbody>
                        </table>
                    </td>
                    <td style='width:50%; padding-left:4px;'>
                        <table class='bordered'>
                            <thead>
                                <tr><th colspan='4' class='center'>ADMINISTRATIVE CLEARANCE</th></tr>
                                <tr>
                                    <th style='width:39%;'>Office / Requirement</th>
                                    <th style='width:13%;'>Status</th>
                                    <th style='width:31%;'>Signature / Name</th>
                                    <th style='width:17%;'>Date</th>
                                </tr>
                            </thead>
                            <tbody>{$adminRows}</tbody>
                        </table>
                    </td>
                </tr>
            </table>

            <table style='margin-top: 10px;'>
                <tr>
                    <td style='width:33%; padding: 0 12px;'>
                        <div class='sign-line'>Chief, Student Services Division</div>
                        <div class='center small muted'>Recommending approval</div>
                    </td>
                    <td style='width:33%; padding: 0 12px;'>
                        <div class='sign-line'>Chief, Curriculum & Instruction Division</div>
                        <div class='center small muted'>Recommending approval</div>
                    </td>
                    <td style='width:33%; padding: 0 12px;'>
                        <div class='sign-line'>Campus Director</div>
                        <div class='center small muted'>Approved</div>
                    </td>
                </tr>
            </table>

            <div class='small muted' style='margin-top:7px;'>
                Generated by CRCMIS on ".now()->format('F d, Y h:i A').".
                This digital copy reflects the current clearance status in the system.
            </div>
        </body>
        </html>";
    }

    private function rows($items, int $minimumRows): string
    {
        $rows = $items->map(fn (StudentClearanceItem $item) => $this->row($item))->implode('');
        $remaining = max(0, $minimumRows - $items->count());

        for ($i = 0; $i < $remaining; $i++) {
            $rows .= "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>";
        }

        return $rows;
    }

    private function row(StudentClearanceItem $item): string
    {
        $name = $item->signer?->name ?? ($item->assignedUser?->name ?? '');
        $date = $item->signed_at?->format('m/d/Y') ?? '';
        $note = $item->blocker_summary ?: $item->accountability;
        $label = $this->h($item->requirement_label);

        if ($note) {
            $label .= "<div class='small muted'>".$this->h($note).'</div>';
        }

        return '<tr>'
            .'<td>'.$label.'</td>'
            .'<td class="center">'.$this->h($this->statusLabel($item->status)).'</td>'
            .'<td>'.$this->h($name).'</td>'
            .'<td class="center">'.$this->h($date).'</td>'
            .'</tr>';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'not_applicable' => 'N/A',
            'ready_for_adviser' => 'For Adviser',
            'pending_registrar' => 'For Registrar',
            'with_accountability', 'hold' => 'Hold',
            default => ucwords(str_replace('_', ' ', (string) $status)),
        };
    }

    private function h(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
