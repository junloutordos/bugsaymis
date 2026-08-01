<?php

namespace App\Services\ScienceLab;

use App\Models\ScienceLab\LabEquipmentRequest;
use App\Models\ScienceLab\LabEquipment;
use App\Models\ScienceLab\LabReagentRequest;
use App\Models\ScienceLab\LabReservation;
use Illuminate\Support\Collection;
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

        return $this->stream($body, 'Laboratory-Reservation-' . $r->control_no, 'A4', 'PSHS-00-F-CID-05-Ver02-Rev0-02/01/20');
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
                'Received by' => [$r->received_by_name, 'Borrower — wet signature', null],
                'Received and Inspected by' => [$r->returnReceiver?->name, 'SRS/SRA', $r->returned_at],
            ])}
            {$this->signatures([
                'Requested by' => [$r->requester_name, 'Requestor', $r->created_at],
                'Endorsed by' => [$r->endorser?->name, 'Subject Teacher/Unit Head', $r->endorsed_at],
                'Approved by' => [$r->approver?->name, 'SRS/SRA', $r->approved_at],
            ])}
        ";

        return $this->stream($body, 'Equipment-Accountability-' . $r->control_no, 'A4', 'PSHS-00-F-CID-20-Ver02-Rev0-02/01/20');
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
            {$this->signatures(['Received by' => [$r->received_by_name, 'Requester — wet signature', null]])}
            {$this->signatures([
                'Requested by' => [$r->requester_name, 'Requestor', $r->created_at],
                'Endorsed by' => [$r->endorser?->name, 'Subject Teacher/Unit Head', $r->endorsed_at],
                'Approved by' => [$r->approver?->name, 'SRS/SRA (Releasing Unit)', $r->approved_at],
            ])}
        ";

        return $this->stream($body, 'Reagent-Request-' . $r->control_no, 'A4', 'PSHS-00-F-CID-19-Ver02-Rev0-02/01/20');
    }

    public function preventiveMaintenance(Collection $schedules, ?string $schoolYear): StreamedResponse
    {
        $months = [8 => 'AUG', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC', 1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MAY', 6 => 'JUN', 7 => 'JUL'];
        $header = '<th>No.</th><th>Equipment</th><th>Property No.</th><th>Location</th><th>Frequency</th>';
        foreach ($months as $label) {
            $header .= '<th>' . $label . '</th>';
        }
        $glyphs = ['as_scheduled' => '&#10003;', 'not_as_scheduled' => '&#9675;', 'not_performed' => '&#10007;', 'none' => ''];
        $rows = '';
        foreach ($schedules->values() as $index => $schedule) {
            $records = $schedule->records->keyBy('month');
            $cells = '';
            foreach ($months as $month => $label) {
                $cells .= '<td class="c">' . ($glyphs[$records->get($month)?->status ?? 'none'] ?? '') . '</td>';
            }
            $rows .= '<tr><td class="c">' . ($index + 1) . '</td><td>' . $this->e($schedule->equipment?->description) . '</td><td>' . $this->e($schedule->equipment?->property_no) . '</td><td>' . $this->e($schedule->equipment?->room?->name) . '</td><td class="c">' . $this->e($schedule->frequency) . '</td>' . $cells . '</tr>';
        }

        $body = '<h2>PREVENTIVE MAINTENANCE SCHEDULE FOR LABORATORY EQUIPMENT</h2><div class="meta">School Year: <b>' . $this->e($schoolYear) . '</b></div>'
            . '<table class="items compact"><thead><tr>' . $header . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<p class="legend">Legend: &#10003; As scheduled &nbsp;&nbsp; &#9675; Not as scheduled &nbsp;&nbsp; &#10007; Not performed</p>'
            . $this->signatures(['Prepared by' => [$schedules->first()?->preparedBy?->name, 'SRS/SRA', null], 'Noted by' => [$schedules->first()?->notedBy?->name, 'Academic Unit Head', null]]);

        return $this->stream($body, 'Preventive-Maintenance-Schedule', 'A4-L', 'PSHS-00-F-CID-08-Ver02-Rev0-02/01/20');
    }

    public function calibrationSchedule(Collection $schedules, ?string $schoolYear): StreamedResponse
    {
        $months = [7 => 'JUL', 8 => 'AUG', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC', 1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MAY', 6 => 'JUN'];
        $monthHeader = '';
        foreach ($months as $label) {
            $monthHeader .= '<th colspan="2">' . $label . '</th>';
        }
        $subHeader = '';
        foreach ($months as $label) {
            $subHeader .= '<th>T</th><th>A</th>';
        }
        $rows = '';
        foreach ($schedules->values() as $index => $schedule) {
            $events = $schedule->events->groupBy(fn ($event) => $event->month ?: optional($event->target_date)->month);
            $cells = '';
            foreach ($months as $month => $label) {
                $event = $events->get($month)?->first();
                $cells .= '<td class="c">' . $this->e(optional($event?->target_date)->format('d')) . '</td><td class="c">' . $this->e(optional($event?->actual_date)->format('d')) . '</td>';
            }
            $rows .= '<tr><td class="c">' . ($index + 1) . '</td><td>' . $this->e($schedule->equipment?->description) . '</td><td>' . $this->e($schedule->equipment?->property_no) . '</td><td>' . $this->e($schedule->equipment?->room?->name) . '</td>' . $cells . '<td>' . $this->e($schedule->remarks) . '</td></tr>';
        }

        $body = '<h2>CALIBRATION SCHEDULE FOR LABORATORY EQUIPMENT</h2><div class="meta">School Year: <b>' . $this->e($schoolYear) . '</b></div>'
            . '<table class="items compact"><thead><tr><th rowspan="2">No.</th><th rowspan="2">Equipment</th><th rowspan="2">Property No.</th><th rowspan="2">Lab</th>' . $monthHeader . '<th rowspan="2">Remarks</th></tr><tr>' . $subHeader . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<p class="legend">T — Target day &nbsp;&nbsp; A — Actual day. Calibration certificates remain attached to the corresponding electronic record.</p>'
            . $this->signatures(['Prepared by' => [$schedules->first()?->preparedBy?->name, 'SRS/SRA', null], 'Approved by' => [$schedules->first()?->approver?->name, 'Academic Unit Head', $schedules->first()?->approved_at]]);

        return $this->stream($body, 'Calibration-Schedule', 'A4-L', 'PSHS-00-F-CID-09-Ver02-Rev0-02/01/20');
    }

    public function serviceForm(LabEquipment $equipment): StreamedResponse
    {
        $logs = $equipment->relationLoaded('serviceLogs')
            ? $equipment->serviceLogs
            : $equipment->serviceLogs()->with('inputtedBy:id,name')->orderBy('log_date')->get();
        $rows = '';
        foreach ($logs as $log) {
            $rows .= '<tr><td>' . $this->e(optional($log->log_date)->format('M d, Y')) . '</td><td>' . $this->e($log->particulars) . '</td><td class="c">' . ($log->type === 'preventive' ? '&#10003;' : '') . '</td><td class="c">' . ($log->type === 'corrective' ? '&#10003;' : '') . '</td><td>' . $this->e($log->remarks_reference) . '</td><td class="r">' . number_format((float) $log->cost_of_materials, 2) . '</td><td>' . $this->e($log->inputtedBy?->name) . '</td><td>' . $this->e($log->serviced_by) . '</td></tr>';
        }

        $body = '<h2>REPAIR / SERVICE FORM</h2><table class="grid"><tr><td class="l">Equipment</td><td>' . $this->e($equipment->description) . '</td><td class="l">Property No.</td><td>' . $this->e($equipment->property_no) . '</td></tr><tr><td class="l">Model / Serial No.</td><td>' . $this->e(trim(($equipment->model ?? '') . ' / ' . ($equipment->serial_number ?? ''), ' /')) . '</td><td class="l">Location</td><td>' . $this->e($equipment->room?->name) . '</td></tr></table>'
            . '<table class="items"><colgroup><col style="width:9%"><col style="width:17%"><col style="width:11%"><col style="width:11%"><col style="width:15%"><col style="width:13%"><col style="width:12%"><col style="width:12%"></colgroup><thead><tr><th rowspan="2">Date</th><th rowspan="2">Particulars</th><th colspan="2">Maintenance</th><th rowspan="2">Remarks / Reference</th><th rowspan="2">Cost of Materials</th><th rowspan="2">Inputted By</th><th rowspan="2">Serviced By</th></tr><tr><th>Preventive</th><th>Corrective</th></tr></thead><tbody>' . $rows . '</tbody></table>';

        return $this->stream($body, 'Repair-Service-' . ($equipment->property_no ?: $equipment->id), 'A4', 'PSHS-00-F-CID-10-Ver02-Rev0-02/01/20');
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
        $width = floor(100 / max(1, count($blocks)));
        foreach ($blocks as $label => [$name, $role, $at]) {
            $stamp = $at ? "<div class='ts'>Signed " . $at->format('M d, Y h:i A') . '</div>' : '';
            $cells .= "<td class='sig' style='width:{$width}%'>
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

    private function stream(string $body, string $filename, string $format = 'A4', string $formCode = ''): StreamedResponse
    {
        $css = "
            body{font-family:sans-serif;font-size:10pt;color:#111}
            h2{text-align:center;font-size:11pt;margin:0 0 4px}
            .meta{text-align:right;font-size:9pt;margin-bottom:6px}
            .campus{text-align:center;font-size:9pt;font-weight:bold}
            table.grid{width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:8px}
            table.grid td{border:0.4pt solid #999;padding:3px 5px;font-size:9pt}
            table.grid td.l{background:#f2f2f2;font-weight:bold;width:20%}
            table.items{width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:8px}
            table.items th,table.items td{border:0.5pt solid #444;padding:4px 5px;font-size:9pt;word-wrap:break-word}
            table.items th{background:#eee}
            td.c{text-align:center}
            td.r{text-align:right}
            .note{margin:6px 0 2px;font-size:9pt}
            .legend{font-size:8pt;margin-top:4px}
            table.compact th,table.compact td{padding:2px;font-size:7pt}
            ol.students{font-size:9pt}
            .muted{color:#888}
            table.sigs{width:100%;table-layout:fixed;margin-top:16px}
            table.sigs td.sig{vertical-align:top;padding:5px;font-size:7.5pt;word-wrap:break-word}
            .sig .lbl{color:#555}
            .sig .nm{border-bottom:0.5pt solid #333;margin-top:18px;font-weight:bold}
            .sig .role{font-size:8pt;color:#555}
            .sig .ts{font-size:7pt;color:#2563eb;margin-top:2px}
        ";

        $logo = public_path('pshslogo_sm.png');
        $headerLogo = is_file($logo) ? '<img src="' . $logo . '" style="height:48px">' : '';
        $html = "<style>{$css}</style>
            <table style='width:100%;border-collapse:collapse;margin-bottom:7px'><tr><td style='width:60px;border:0'>{$headerLogo}</td><td class='campus' style='border:0'>Republic of the Philippines<br><b>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</b><br>CARAGA REGION CAMPUS</td><td style='width:60px;border:0'></td></tr></table>
            {$body}";

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => $format,
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'margin_left'   => 14,
            'margin_right'  => 14,
            'tempDir'       => sys_get_temp_dir(),
        ]);
        if ($formCode) {
            $mpdf->SetHTMLFooter('<table style="width:100%;font-size:7pt;border-top:0.4pt solid #777"><tr><td>' . $this->e($formCode) . '</td><td style="text-align:right">Page {PAGENO} of {nbpg}</td></tr></table>');
        }
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
