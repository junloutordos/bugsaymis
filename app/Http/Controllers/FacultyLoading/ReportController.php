<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\OverloadComputation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;

class ReportController extends Controller
{
    // ── Faculty Load Summary Report ───────────────────────────────────────────

    public function loads(Request $request): \Inertia\Response
    {
        $this->authorize('faculty_loading.reports');

        $termId = $request->input('term_id');
        $status = $request->input('status');
        $search = $request->input('search');

        $loads = $this->queryLoads($termId, $status, $search);

        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $currentTerm = AcademicTerm::where('is_current', true)->first();

        return Inertia::render('FacultyLoading/Reports/Loads', [
            'loads'       => $loads,
            'terms'       => $terms,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'     => $request->only(['term_id', 'status', 'search']),
            'summary'     => $this->loadsSummary($loads),
        ]);
    }

    public function exportLoads(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('faculty_loading.reports');

        $loads = $this->queryLoads(
            $request->input('term_id'),
            $request->input('status'),
            $request->input('search')
        );

        $term = $request->input('term_id')
            ? AcademicTerm::find($request->input('term_id'))
            : AcademicTerm::where('is_current', true)->first();

        $filename = 'faculty-load-report-' . ($term ? str($term->full_label)->slug() : 'all') . '.csv';

        return response()->streamDownload(function () use ($loads) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                '#', 'Faculty Name', 'Position', 'Salary Grade',
                'Teaching', 'Research', 'Admin', 'Co-Curricular', 'Committee',
                'Total Units', 'Full Load', 'Overload Units', 'Status', 'Overload Approved',
            ]);

            foreach ($loads as $i => $l) {
                fputcsv($out, [
                    $i + 1,
                    $l['faculty_name'],
                    $l['position'] ?? '',
                    $l['salary_grade'] ?? '',
                    $l['teaching_units'],
                    $l['research_units'],
                    $l['admin_units'],
                    $l['cocurricular_units'],
                    $l['committee_units'],
                    $l['total_units'],
                    $l['full_load_threshold'],
                    $l['overload_units'],
                    $l['load_status'],
                    $l['overload_approved'] ? 'Yes' : 'No',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ── Overload Pay Report ───────────────────────────────────────────────────

    public function overloadPay(Request $request): \Inertia\Response
    {
        $this->authorize('faculty_loading.reports');

        $termId = $request->input('term_id');
        $status = $request->input('status');

        $computations = $this->queryComputations($termId, $status);

        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $currentTerm = AcademicTerm::where('is_current', true)->first();

        $grandTotal = collect($computations)->sum('total_overload_pay');

        return Inertia::render('FacultyLoading/Reports/OverloadPay', [
            'computations' => $computations,
            'terms'        => $terms,
            'currentTerm'  => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'      => $request->only(['term_id', 'status']),
            'grandTotal'   => $grandTotal,
        ]);
    }

    public function exportOverloadPay(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('faculty_loading.reports');

        $computations = $this->queryComputations(
            $request->input('term_id'),
            $request->input('status')
        );

        $term = $request->input('term_id')
            ? AcademicTerm::find($request->input('term_id'))
            : AcademicTerm::where('is_current', true)->first();

        $filename = 'overload-pay-report-' . ($term ? str($term->full_label)->slug() : 'all') . '.csv';

        return response()->streamDownload(function () use ($computations) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                '#', 'Faculty Name', 'Position', 'Salary Grade',
                'Annual Rate (₱)', 'PHTR (₱)', 'Overload Units', 'Overload Hours', 'Weeks',
                'Total Overload Pay (₱)', 'Status', 'Approved At',
            ]);

            $grandTotal = 0;
            foreach ($computations as $i => $c) {
                fputcsv($out, [
                    $i + 1,
                    $c['faculty_name'],
                    $c['position'] ?? '',
                    $c['salary_grade'] ?? '',
                    number_format($c['annual_rate'], 2),
                    number_format($c['phtr'], 4),
                    $c['overload_units'],
                    $c['overload_hours'],
                    $c['term_weeks'],
                    number_format($c['total_overload_pay'], 2),
                    $c['status'],
                    $c['approved_at'] ?? '',
                ]);
                $grandTotal += $c['total_overload_pay'];
            }

            fputcsv($out, ['', '', '', '', '', '', '', '', 'TOTAL', number_format($grandTotal, 2), '', '']);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ── Private query helpers ─────────────────────────────────────────────────

    private function queryLoads(?string $termId, ?string $status, ?string $search): array
    {
        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $resolvedTermId = $termId ?? $currentTerm?->id;

        return FacultyLoad::with(['faculty:id,name,sst_position_id', 'faculty.sstPosition', 'academicTerm.schoolYear'])
            ->when($resolvedTermId, fn ($q) => $q->where('academic_term_id', $resolvedTermId))
            ->when($status, fn ($q) => $q->where('load_status', $status))
            ->when($search, fn ($q) => $q->whereHas('faculty', fn ($u) => $u->where('name', 'like', "%{$search}%")))
            ->orderBy(
                \App\Models\User::select('name')->whereColumn('users.id', 'faculty_loads.user_id')->limit(1)
            )
            ->get()
            ->map(fn ($l) => [
                'id'                 => $l->id,
                'faculty_name'       => $l->faculty?->name ?? '—',
                'position'           => $l->faculty?->sstPosition?->code,
                'salary_grade'       => $l->faculty?->sstPosition?->salary_grade,
                'teaching_units'     => (float) $l->teaching_units,
                'research_units'     => (float) $l->research_units,
                'admin_units'        => (float) $l->admin_units,
                'cocurricular_units' => (float) $l->cocurricular_units,
                'committee_units'    => (float) $l->committee_units,
                'total_units'        => (float) $l->total_units,
                'full_load_threshold'=> (float) $l->full_load_threshold,
                'overload_units'     => $l->overload_units,
                'load_status'        => $l->load_status,
                'overload_approved'  => (bool) $l->overload_approved,
                'term_label'         => $l->academicTerm?->full_label,
            ])
            ->toArray();
    }

    private function queryComputations(?string $termId, ?string $status): array
    {
        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $resolvedTermId = $termId ?? $currentTerm?->id;

        return OverloadComputation::with(['faculty:id,name,sst_position_id', 'faculty.sstPosition', 'academicTerm.schoolYear'])
            ->when($resolvedTermId, fn ($q) => $q->where('academic_term_id', $resolvedTermId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy(
                \App\Models\User::select('name')->whereColumn('users.id', 'overload_computations.user_id')->limit(1)
            )
            ->get()
            ->map(fn ($c) => [
                'id'                 => $c->id,
                'faculty_name'       => $c->faculty?->name ?? '—',
                'position'           => $c->faculty?->sstPosition?->code,
                'salary_grade'       => $c->faculty?->sstPosition?->salary_grade,
                'annual_rate'        => (float) $c->annual_rate,
                'phtr'               => (float) $c->phtr,
                'overload_units'     => (float) $c->overload_units,
                'overload_hours'     => (float) $c->overload_hours,
                'term_weeks'         => $c->term_weeks,
                'total_overload_pay' => (float) $c->total_overload_pay,
                'status'             => $c->status,
                'approved_at'        => $c->approved_at?->toDateString(),
                'term_label'         => $c->academicTerm?->full_label,
            ])
            ->toArray();
    }

    private function loadsSummary(array $loads): array
    {
        $col = collect($loads);
        return [
            'total'     => $col->count(),
            'overload'  => $col->where('load_status', 'overload')->count(),
            'full_load' => $col->where('load_status', 'full_load')->count(),
            'underload' => $col->where('load_status', 'underload')->count(),
        ];
    }
}
