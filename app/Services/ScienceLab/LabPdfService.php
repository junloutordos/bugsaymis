<?php

namespace App\Services\ScienceLab;

use App\Models\ScienceLab\LabEquipmentRequest;
use App\Models\ScienceLab\LabReagentRequest;
use App\Models\ScienceLab\LabReservation;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders the official PSHS-CRC laboratory forms as PDFs (mPDF).
 * Uses sys_get_temp_dir() for the temp dir per project policy.
 */
class LabPdfService
{
    public function reservation(LabReservation $r): StreamedResponse
    {
        $students = $this->studentList($r->student_group);
        $rows = "
            <tr><td class='l'>Grade Level &amp; Section</td><td>{$this->e($r->grade_level_section)}</td>
                <td class='l'>No. of Students</td><td>{$this->e($r->number_of_students)}</td></tr>
            <tr><td class='l'>Subject</td><td>{$this->e($r->subject)}</td>
                <td class='l'>Teacher In-Charge</td><td>{$this->e($r->teacher_in_charge)}</td></tr>
            <tr><td class='l'>Date/Inclusive Dates</td><td>{$this->dates($r)}</td>
                <td class='l'>Inclusive Time</td><td>{$this->times($r)}</td></tr>
            <tr><td class='l'>Preferred Lab Room</td><td colspan='3'>{$this->e($r->room?->name)}</td></tr>
            <tr><td class='l'>Requested by</td><td>{$this->e($r->requester_name)}</td>
                <td class='l'>Date Requested</td><td>{$this->e(optional($r->created_at)->format('M d, Y'))}</td></tr>
        ";

        $body = "
            <h2>LABORATORY RESERVATION FORM</h2>
            <div class='meta'>Control No: <b>{$this->e($r->control_no)}</b> &nbsp;&nbsp; SY: {$this->e($r->schoolYear?->name)}</div>
            <table class='grid'>{$rows}</table>
            <p class='note'><i>If user of the lab is a group, list down the names of students:</i></p>
            {$students}
            {$this->signatures([
                'Endorsed by' => [$r->endorser?->name, 'Subject Teacher/Unit Head', $r->endorsed_at],
                'Approved by' => [$r->approver?->name, 'SRS/SRA', $r->approved_at],
            ])}
        ";

        return $this->stream($body, 'Laboratory-Reservation-' . $r->control_no);
    }

    public function equipmentRequest(LabEquipmentRequest $r): StreamedResponse
    {
        $lines = '';
        foreach ($r->items as $it) {
            $lines .= "<tr>
                <td class='c'>{$this->num($it->quantity)}</td>
                <td>{$this->e($it->item)}</td>
                <td>{$this->e($it->description)}</td>
                <td>{$this->e($it->issued_condition)}</td>
                <td>{$this->e($it->returned_condition)}</td></tr>";
        }
        $students = $this->studentList($r->student_group);

        $body = "
            <h2>LABORATORY REQUEST AND EQUIPMENT ACCOUNTABILITY FORM</h2>
            <div class='meta'>Control No: <b>{$this->e($r->control_no)}</b> &nbsp;&nbsp; SY: {$this->e($r->schoolYear?->name)}</div>
            <table class='grid'>
                <tr><td class='l'>Grade Level &amp; Section</td><td>{$this->e($r->grade_level_section)}</td>
                    <td class='l'>No. of Students</td><td>{$this->e($r->number_of_students)}</td></tr>
                <tr><td class='l'>Subject</td><td>{$this->e($r->subject)}</td>
                    <td class='l'>Concurrent Topic</td><td>{$this->e($r->concurrent_topic)}</td></tr>
                <tr><td class='l'>Unit</td><td>{$this->e($r->unit)}</td>
                    <td class='l'>Teacher In-Charge</td><td>{$this->e($r->teacher_in_charge)}</td></tr>
                <tr><td class='l'>Venue</td><td>{$this->e($r->venue)}</td>
                    <td class='l'>Date/Time</td><td>{$this->dates($r)} {$this->times($r)}</td></tr>
            </table>
            <p class='note'><i>Materials / Equipment Needed:</i></p>
            <table class='items'>
                <thead><tr><th>Qty</th><th>Item</th><th>Description</th><th>Issued<br>Condition/Remarks</th><th>Returned<br>Condition/Remarks</th></tr></thead>
                <tbody>{$lines}</tbody>
            </table>
            <p class='note'><i>Students:</i></p>{$students}
            {$this->signatures([
                'Endorsed by' => [$r->endorser?->name, 'Subject Teacher/Unit Head', $r->endorsed_at],
                'Approved by' => [$r->approver?->name, 'SRS/SRA', $r->approved_at],
                'Received/Inspected by' => [$r->issuer?->name, 'SRS/SRA', $r->issued_at],
            ])}
        ";

        return $this->stream($body, 'Equipment-Accountability-' . $r->control_no);
    }

    public function reagentRequest(LabReagentRequest $r): StreamedResponse
    {
        $lines = '';
        foreach ($r->items as $it) {
            $sds = $it->sds_read ? '&#10003;' : '&#10007;';
            $lines .= "<tr>
                <td class='c'>{$this->num($it->quantity)}</td>
                <td>{$this->e($it->reagent)}</td>
                <td class='c'>{$sds}</td>
                <td>{$this->e($it->issued_amount)}</td></tr>";
        }
        $students = $this->studentList($r->student_group);

        $body = "
            <h2>REAGENT REQUEST FORM</h2>
            <div class='meta'>Control No: <b>{$this->e($r->control_no)}</b> &nbsp;&nbsp; SY: {$this->e($r->schoolYear?->name)}</div>
            <table class='grid'>
                <tr><td class='l'>Grade Level &amp; Section</td><td>{$this->e($r->grade_level_section)}</td>
                    <td class='l'>No. of Students</td><td>{$this->e($r->number_of_students)}</td></tr>
                <tr><td class='l'>Subject</td><td>{$this->e($r->subject)}</td>
                    <td class='l'>Concurrent Topic</td><td>{$this->e($r->concurrent_topic)}</td></tr>
                <tr><td class='l'>Unit</td><td>{$this->e($r->unit)}</td>
                    <td class='l'>Teacher In-Charge</td><td>{$this->e($r->teacher_in_charge)}</td></tr>
                <tr><td class='l'>Venue</td><td>{$this->e($r->venue)}</td>
                    <td class='l'>Date/Time</td><td>{$this->dates($r)} {$this->times($r)}</td></tr>
            </table>
            <p class='note'><i>Reagent Needed (SDS read: &#10003; yes / &#10007; no):</i></p>
            <table class='items'>
                <thead><tr><th>Qty</th><th>Reagent</th><th>SDS</th><th>Issued Amount/Remarks</th></tr></thead>
                <tbody>{$lines}</tbody>
            </table>
            <p class='note'><i>Students:</i></p>{$students}
            {$this->signatures([
                'Endorsed by' => [$r->endorser?->name, 'Subject Teacher/Unit Head', $r->endorsed_at],
                'Approved by' => [$r->approver?->name, 'SRS/SRA (Releasing Unit)', $r->approved_at],
                'Released by' => [$r->releaser?->name, 'SRS/SRA', $r->released_at],
            ])}
        ";

        return $this->stream($body, 'Reagent-Request-' . $r->control_no);
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    private function studentList($group): string
    {
        $names = is_array($group) ? array_filter($group) : [];
        if (empty($names)) {
            return "<p class='muted'>—</p>";
        }
        $out = '<ol class="students">';
        foreach ($names as $n) {
            $out .= '<li>' . $this->e($n) . '</li>';
        }

        return $out . '</ol>';
    }

    private function signatures(array $blocks): string
    {
        $cells = '';
        foreach ($blocks as $label => [$name, $role, $at]) {
            $stamp = $at ? "<div class='ts'>Signed " . $at->format('M d, Y h:i A') . '</div>' : '';
            $cells .= "<td class='sig'>
                <div class='lbl'>{$label}:</div>
                <div class='nm'>" . $this->e($name ?: '________________') . "</div>
                <div class='role'>{$this->e($role)}</div>{$stamp}
            </td>";
        }

        return "<table class='sigs'><tr>{$cells}</tr></table>";
    }

    private function dates($r): string
    {
        $s = optional($r->date_start)->format('M d, Y');
        $e = $r->date_end ? ' – ' . optional($r->date_end)->format('M d, Y') : '';

        return $this->e($s . $e);
    }

    private function times($r): string
    {
        if (! $r->time_start && ! $r->time_end) {
            return '—';
        }

        return $this->e(trim(($r->time_start ?? '') . ' – ' . ($r->time_end ?? '')));
    }

    private function num($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    }

    private function e($v): string
    {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
    }

    private function stream(string $body, string $filename): StreamedResponse
    {
        $css = "
            body{font-family:sans-serif;font-size:10pt;color:#111}
            h2{text-align:center;font-size:12pt;margin:0 0 4px}
            .meta{text-align:right;font-size:9pt;margin-bottom:6px}
            .campus{text-align:center;font-size:9pt;font-weight:bold}
            table.grid{width:100%;border-collapse:collapse;margin-bottom:8px}
            table.grid td{border:0.4pt solid #999;padding:3px 5px;font-size:9pt}
            table.grid td.l{background:#f2f2f2;font-weight:bold;width:20%}
            table.items{width:100%;border-collapse:collapse;margin-bottom:8px}
            table.items th,table.items td{border:0.5pt solid #444;padding:4px 5px;font-size:9pt}
            table.items th{background:#eee}
            td.c{text-align:center}
            .note{margin:6px 0 2px;font-size:9pt}
            ol.students{font-size:9pt}
            .muted{color:#888}
            table.sigs{width:100%;margin-top:22px}
            table.sigs td.sig{width:33%;vertical-align:top;padding:6px;font-size:8.5pt}
            .sig .lbl{color:#555}
            .sig .nm{border-bottom:0.5pt solid #333;margin-top:18px;font-weight:bold}
            .sig .role{font-size:8pt;color:#555}
            .sig .ts{font-size:7pt;color:#2563eb;margin-top:2px}
        ";

        $html = "<style>{$css}</style>
            <div class='campus'>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM<br>CARAGA REGION CAMPUS</div>
            {$body}";

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'margin_left'   => 14,
            'margin_right'  => 14,
            'tempDir'       => sys_get_temp_dir(),
        ]);
        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML($html);
        $bytes = $mpdf->Output('', 'S');

        return new StreamedResponse(fn () => print($bytes), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"',
            'Content-Length'      => strlen($bytes),
        ]);
    }
}
