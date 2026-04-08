<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\OrganizationalUnit;
use App\Models\HR\EmployeeUnitAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Inertia\Inertia;

class OrgExportController extends Controller
{
    // ── Print page (Inertia) ───────────────────────────────────────────────────

    /**
     * GET /hr/org/print
     * Inertia print-optimized view (browser Ctrl+P).
     */
    public function print(Request $request)
    {
        $this->authorize('org.export');

        $tree = OrganizationalUnit::getFullTree(onlyActive: true);

        return Inertia::render('HR/OrgStructure/Print', [
            'tree'        => $tree,
            'generatedAt' => now()->format('F j, Y g:i A'),
        ]);
    }

    // ── PDF export ─────────────────────────────────────────────────────────────

    /**
     * GET /hr/org/export/pdf
     * Generates a multi-page org chart PDF using mPDF.
     */
    public function pdf(Request $request): Response
    {
        $this->authorize('org.export');

        $tree        = OrganizationalUnit::getFullTree(onlyActive: true);
        $generatedAt = now()->format('F j, Y g:i A');

        $html = view('hr.org.export-pdf', compact('tree', 'generatedAt'))->render();

        $mpdf = new Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'orientation'       => 'P',
            'margin_left'       => 15,
            'margin_right'      => 15,
            'margin_top'        => 20,
            'margin_bottom'     => 15,
            'default_font'      => 'Arial',
            'default_font_size' => 9,
        ]);

        $mpdf->SetTitle('Organizational Structure');
        $mpdf->SetAuthor('BUGSAYMIS');
        $mpdf->WriteHTML($html);

        $content = $mpdf->Output('', 'S');

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="org-structure-' . now()->format('Ymd') . '.pdf"',
            'Cache-Control'       => 'no-cache',
        ]);
    }

    // ── CSV export ─────────────────────────────────────────────────────────────

    /**
     * GET /hr/org/export/units-csv
     * Flat CSV of all organizational units.
     */
    public function unitsCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('org.export');

        $units = OrganizationalUnit::orderBy('depth')->orderBy('order_index')->orderBy('name')
            ->with('parent:id,name')
            ->get();

        $filename = 'org-units-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($units) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID', 'Code', 'Name', 'Short Name', 'Type',
                'Parent', 'Depth', 'Status', 'Established Date',
                'Legal Basis', 'Order Index',
            ]);
            foreach ($units as $u) {
                fputcsv($out, [
                    $u->id,
                    $u->code,
                    $u->name,
                    $u->short_name ?? '',
                    $u->type,
                    $u->parent?->name ?? '',
                    $u->depth,
                    $u->is_active ? 'Active' : 'Inactive',
                    $u->established_date?->format('Y-m-d') ?? '',
                    $u->legal_basis ?? '',
                    $u->order_index,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * GET /hr/org/export/assignments-csv
     * Flat CSV of all current employee-unit assignments.
     */
    public function assignmentsCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('org.export');

        $assignments = EmployeeUnitAssignment::whereNull('end_date')
            ->whereNull('deleted_at')
            ->with([
                'user:id,name,badge_id,emp_category',
                'unit:id,name,code,type',
            ])
            ->orderBy('organizational_unit_id')
            ->get();

        $filename = 'employee-assignments-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($assignments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Badge ID', 'Employee Name', 'Category',
                'Unit Code', 'Unit Name', 'Unit Type',
                'Assignment Type', 'Appointment Type',
                'Position Title', 'Item No.', 'Effective Date', 'Primary',
            ]);
            foreach ($assignments as $a) {
                fputcsv($out, [
                    $a->user?->badge_id ?? '',
                    $a->user?->name ?? '',
                    $a->user?->emp_category ?? '',
                    $a->unit?->code ?? '',
                    $a->unit?->name ?? '',
                    $a->unit?->type ?? '',
                    $a->assignment_type ?? '',
                    $a->appointment_type ?? '',
                    $a->position_title ?? '',
                    $a->item_number ?? '',
                    $a->effective_date?->format('Y-m-d') ?? '',
                    $a->is_primary ? 'Yes' : 'No',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
