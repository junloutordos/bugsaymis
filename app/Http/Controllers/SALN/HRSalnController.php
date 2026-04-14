<?php

namespace App\Http\Controllers\SALN;

use App\Enums\ApprovalStep;
use App\Http\Controllers\Controller;
use App\Models\SALN\SalnRecord;
use App\Models\User;
use App\Services\SnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HRSalnController extends Controller
{
    public function __construct(private SnapshotService $snapshots) {}
    // ══════════════════════════════════════════════════════════════════════════
    // HR OFFICE — Final filing & reports
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * View all SALN records with filters.
     */
    public function index(Request $request): Response
    {
        $this->authorize('saln.view_all');

        $query = SalnRecord::with('user:id,name,position')
            ->orderByDesc('year')
            ->orderBy('status');

        // Filter by year
        if ($request->filled('year')) {
            $query->where('year', (int) $request->year);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by employee name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $records = $query->paginate(25)->withQueryString()->through(fn ($r) => [
            'id'                 => $r->id,
            'year'               => $r->year,
            'status'             => $r->status,
            'status_label'       => $r->status_label,
            'status_color'       => $r->status_color,
            'net_worth'          => $r->net_worth,
            'total_assets'       => $r->total_assets,
            'total_liabilities'  => $r->total_liabilities,
            'submitted_at'       => $r->submitted_at?->toDateString(),
            'approved_at'        => $r->approved_at?->toDateString(),
            'filed_at'           => $r->filed_at?->toDateString(),
            'user'               => $r->user,
        ]);

        // Available years for filter dropdown
        $years = SalnRecord::selectRaw('DISTINCT year')->orderByDesc('year')->pluck('year');

        return Inertia::render('SALN/HR/Index', [
            'records'  => $records,
            'years'    => $years,
            'filters'  => $request->only('year', 'status', 'search'),
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Mark an approved SALN as officially filed.
     */
    public function file(SalnRecord $saln): RedirectResponse
    {
        $this->authorize('saln.file');

        if (! $saln->isApproved()) {
            return back()->with('error', 'Only approved SALNs can be marked as filed.');
        }

        $oldStatus = $saln->status;

        $saln->update([
            'status'   => 'filed',
            'filed_at' => now(),
            'filed_by' => Auth::id(),
        ]);

        $saln->logAction(Auth::id(), 'filed', $oldStatus, 'filed');

        $this->snapshots->recordApproval(
            approvable: $saln,
            step:       ApprovalStep::SALN_FILED,
            sequence:   3,
            action:     'filed',
            approver:   Auth::user(),
        );

        return back()->with('success', "SALN of {$saln->user->name} ({$saln->year}) marked as filed.");
    }

    /**
     * Annual compliance report — summary of filing status per year.
     * Returns: total employees, filed, approved, submitted, draft/returned, non-filers.
     */
    public function annualReport(Request $request): Response
    {
        $this->authorize('saln.view_all');

        $year = (int) ($request->input('year', now()->year));

        // All employees (non-Student/Parent)
        $totalEmployees = User::whereHas('roles', fn ($q) =>
            $q->whereNotIn('name', ['Student', 'Parent'])
        )->where('status', '!=', 'inactive')->count();

        // SALN records for the year grouped by status
        $statusCounts = SalnRecord::where('year', $year)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $filed    = $statusCounts['filed']       ?? 0;
        $approved = $statusCounts['approved']    ?? 0;
        $review   = ($statusCounts['submitted']  ?? 0) + ($statusCounts['under_review'] ?? 0);
        $draft    = ($statusCounts['draft']      ?? 0) + ($statusCounts['returned']     ?? 0);
        $filers   = array_sum($statusCounts);
        $nonFilers= max(0, $totalEmployees - $filers);

        // Per-employee detail for the year
        $detail = SalnRecord::with('user:id,name,position')
            ->where('year', $year)
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => [
                'id'           => $r->id,
                'user'         => $r->user,
                'status'       => $r->status,
                'status_label' => $r->status_label,
                'status_color' => $r->status_color,
                'net_worth'    => $r->net_worth,
                'filed_at'     => $r->filed_at?->toDateString(),
                'submitted_at' => $r->submitted_at?->toDateString(),
            ]);

        return Inertia::render('SALN/HR/AnnualReport', [
            'year'           => $year,
            'totalEmployees' => $totalEmployees,
            'summary'        => compact('filed', 'approved', 'review', 'draft', 'nonFilers', 'filers'),
            'detail'         => $detail,
            'availableYears' => SalnRecord::selectRaw('DISTINCT year')->orderByDesc('year')->pluck('year'),
        ]);
    }

    /**
     * SALN history for a specific employee (multi-year).
     */
    public function employeeHistory(User $user): Response
    {
        $this->authorize('saln.view_all');

        $records = SalnRecord::where('user_id', $user->id)
            ->orderByDesc('year')
            ->get()
            ->map(fn ($r) => [
                'id'               => $r->id,
                'year'             => $r->year,
                'status'           => $r->status,
                'status_label'     => $r->status_label,
                'status_color'     => $r->status_color,
                'total_assets'     => $r->total_assets,
                'total_liabilities'=> $r->total_liabilities,
                'net_worth'        => $r->net_worth,
                'submitted_at'     => $r->submitted_at?->toDateString(),
                'filed_at'         => $r->filed_at?->toDateString(),
            ]);

        return Inertia::render('SALN/HR/EmployeeHistory', [
            'employee' => $user->only('id', 'name', 'position'),
            'records'  => $records,
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function statusOptions(): array
    {
        return [
            ['value' => '',             'label' => 'All Statuses'],
            ['value' => 'draft',        'label' => 'Draft'],
            ['value' => 'submitted',    'label' => 'Submitted'],
            ['value' => 'under_review', 'label' => 'Under Review'],
            ['value' => 'approved',     'label' => 'Approved'],
            ['value' => 'returned',     'label' => 'Returned'],
            ['value' => 'filed',        'label' => 'Filed'],
        ];
    }
}
