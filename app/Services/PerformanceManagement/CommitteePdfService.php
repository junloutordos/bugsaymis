<?php

namespace App\Services\PerformanceManagement;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use Mpdf\Mpdf;

class CommitteePdfService
{
    /** Returns raw PDF bytes for a committee's roster/compliance report. */
    public function exportRoster(Committee $committee, ?int $termId = null): string
    {
        $term = $termId ? AcademicTerm::find($termId) : AcademicTerm::where('is_current', true)->first();

        $committee->loadMissing(['head']);

        $assignments = FacultyCommitteeAssignment::with(['faculty:id,name,position'])
            ->where('committee_id', $committee->id)
            ->when($term, fn ($q) => $q->where('academic_term_id', $term->id))
            ->where('status', 'active')
            ->orderByRaw("CASE role WHEN 'chairperson' THEN 0 WHEN 'co_chair' THEN 1 WHEN 'secretary' THEN 2 ELSE 3 END")
            ->get();

        $html = $this->buildHtml($committee, $term, $assignments);

        $mpdf = new Mpdf([
            'mode'         => 'utf-8',
            'format'       => 'A4',
            'orientation'  => 'P',
            'margin_left'  => 15,
            'margin_right' => 15,
            'margin_top'   => 15,
            'margin_bottom'=> 15,
            'default_font' => 'dejavusans',
            'tempDir'      => sys_get_temp_dir(),
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function buildHtml(Committee $committee, ?AcademicTerm $term, $assignments): string
    {
        $roleLabel = fn ($role) => match ($role) {
            'chairperson' => 'Chairperson',
            'co_chair'    => 'Co-Chairperson',
            'secretary'   => 'Secretary',
            default       => 'Member',
        };

        $rows = '';
        foreach ($assignments as $a) {
            $rows .= '<tr>'
                . '<td>' . e($a->faculty?->name ?? '—') . '</td>'
                . '<td>' . e($a->faculty?->position ?? '—') . '</td>'
                . '<td>' . e($roleLabel($a->role)) . '</td>'
                . '<td style="text-align:center;">' . e((string) (float) $a->load_units) . '</td>'
                . '<td>' . e(ucfirst($a->status)) . '</td>'
                . '</tr>';
        }

        $soLine = $committee->so_number ? '<p><strong>SO Number:</strong> ' . e($committee->so_number) . '</p>' : '';

        return <<<HTML
        <style>
            body { font-family: dejavusans; font-size: 11px; }
            h1 { font-size: 16px; margin-bottom: 4px; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th, td { border: 1px solid #cbd5e1; padding: 6px 8px; }
            th { background-color: #e2e8f0; text-align: left; }
        </style>
        <h1>{$committee->name} — Committee Roster</h1>
        <p><strong>Term:</strong> {$term?->full_label}</p>
        {$soLine}
        <table>
            <thead>
                <tr><th>Name</th><th>Position</th><th>Role</th><th>Load Units</th><th>Status</th></tr>
            </thead>
            <tbody>{$rows}</tbody>
        </table>
        HTML;
    }
}
