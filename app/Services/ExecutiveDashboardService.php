<?php

namespace App\Services;

use App\Models\IPCRRatingPeriod;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Cross-system aggregates for the Executive Dashboard (OCD + Division
 * Chiefs). Whole payload is cached per lens (campus / one division) for an
 * hour; every section is wrapped in rescue() so one broken module never
 * takes down the page (PersonalDashboardService pattern).
 */
class ExecutiveDashboardService
{
    private const CACHE_TTL = 3600;

    /**
     * Request modules aggregated in the Requests section. Statuses are
     * free-text workflow labels — lists verified against prod 2026-07-11.
     */
    private const REQUEST_MODULES = [
        'IT Job'   => ['table' => 'it_job_requests',  'user' => 'user_id',      'completed' => ['Request Completed'],       'declined' => []],
        'Facility' => ['table' => 'facility_requests', 'user' => 'requestor_id', 'completed' => ['OCD Approved', 'Approved'], 'declined' => ['Declined']],
        'Vehicle'  => ['table' => 'vehicle_requests',  'user' => 'requestor_id', 'completed' => ['Completed'],                'declined' => ['Declined']],
        'Service'  => ['table' => 'service_requests',  'user' => 'requestor_id', 'completed' => ['Completed'],                'declined' => ['Declined']],
        'Work'     => ['table' => 'work_requests',     'user' => 'requester_id', 'completed' => ['Completed', 'Rated'],       'declined' => ['Declined']],
        'Travel'   => ['table' => 'travel_requests',   'user' => 'traveler_id',  'completed' => ['completed', 'approved'],    'declined' => ['cancelled', 'returned', 'declined']],
    ];

    public function __construct(private IPCRWorkflowService $ipcr)
    {
    }

    public function cacheKey(?int $divisionId): string
    {
        return 'exec_dashboard_v1_' . ($divisionId ?? 'all');
    }

    public function forget(?int $divisionId): void
    {
        Cache::forget($this->cacheKey($divisionId));
    }

    public function build(?int $divisionId): array
    {
        return Cache::remember($this->cacheKey($divisionId), self::CACHE_TTL, function () use ($divisionId) {
            $data = [
                'workforce'    => $this->rescue('workforce',    fn () => $this->workforce($divisionId)),
                'performance'  => $this->rescue('performance',  fn () => $this->performance($divisionId)),
                'requests'     => $this->rescue('requests',     fn () => $this->requests($divisionId)),
                'satisfaction' => $this->rescue('satisfaction', fn () => $this->satisfaction()),
                'academics'    => $this->rescue('academics',    fn () => $this->academics()),
                'recruitment'  => $this->rescue('recruitment',  fn () => $this->recruitment()),
                'finance'      => $this->rescue('finance',      fn () => $this->finance()),
                'operations'   => $this->rescue('operations',   fn () => $this->operations($divisionId)),
            ];

            $data['attention']   = $this->rescue('attention', fn () => $this->attention($divisionId, $data), []);
            $data['scorecard']   = $divisionId ? null : $this->rescue('scorecard', fn () => $this->scorecard(), []);
            $data['generatedAt'] = now()->toIso8601String();

            return $data;
        });
    }

    private function rescue(string $label, callable $cb, $fallback = null)
    {
        try {
            return $cb();
        } catch (\Throwable $e) {
            Log::warning("Executive dashboard section [{$label}] failed", ['error' => $e->getMessage()]);

            return $fallback;
        }
    }

    /** Active (non-inactive) users of the lens, optionally division-scoped. */
    private function staffQuery(?int $divisionId)
    {
        return DB::table('users')
            ->where('users.status', '<>', 'inactive')
            // NULL emp_category = staff (NOT IN would silently drop NULLs)
            ->where(fn ($q) => $q->whereNull('users.emp_category')
                ->orWhereNotIn('users.emp_category', ['student', 'parent']))
            ->when($divisionId, fn ($q) => $q->where('users.division_id', $divisionId));
    }

    private function staffIds(?int $divisionId)
    {
        return $this->staffQuery($divisionId)->pluck('users.id');
    }

    /* ── 1. Workforce & Leave ─────────────────────────────────────────── */

    private function workforce(?int $divisionId): array
    {
        $byDivision = $this->staffQuery(null)
            ->join('divisions', 'divisions.id', '=', 'users.division_id')
            ->where('divisions.status', 'active')
            ->selectRaw('divisions.division_name as label, COUNT(DISTINCT LOWER(TRIM(users.name))) as total')
            ->groupBy('divisions.division_name')
            ->orderByDesc('total')
            ->get();

        $byCategory = $this->staffQuery($divisionId)
            ->selectRaw("COALESCE(NULLIF(TRIM(emp_category), ''), 'unspecified') as label, COUNT(DISTINCT LOWER(TRIM(name))) as total")
            ->groupBy('label')->orderByDesc('total')->get();

        $bySex = $this->staffQuery($divisionId)
            ->selectRaw("COALESCE(NULLIF(TRIM(LOWER(sex)), ''), 'unspecified') as label, COUNT(DISTINCT LOWER(TRIM(name))) as total")
            ->groupBy('label')->get();

        $staffIds = $this->staffIds($divisionId);

        $leaveByStatus = DB::table('leave_applications')
            ->whereNull('deleted_at')
            ->whereIn('user_id', $staffIds)
            ->whereYear('created_at', now()->year)
            ->selectRaw('status as label, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'label');

        $leaveDaysYtd = (float) DB::table('leave_applications')
            ->whereNull('deleted_at')
            ->whereIn('user_id', $staffIds)
            ->where('status', 'approved')
            ->whereYear('date_from', now()->year)
            ->sum('days_applied');

        $leaveTrend = $this->monthlyTrend('leave_applications', 'created_at', 12,
            fn ($q) => $q->whereNull('deleted_at')->whereIn('user_id', $staffIds));

        $dtrToday = DB::table('dtr_records')
            ->whereIn('user_id', $staffIds)
            ->whereDate('work_date', today())
            ->selectRaw('attendance_status as label, COUNT(*) as total')
            ->groupBy('attendance_status')->pluck('total', 'label');

        return [
            'headcount'     => (int) $this->staffQuery($divisionId)->selectRaw('COUNT(DISTINCT LOWER(TRIM(name))) as c')->value('c'),
            'byDivision'    => $byDivision,
            'byCategory'    => $byCategory,
            'bySex'         => $bySex,
            'leaveByStatus' => $leaveByStatus,
            'leaveDaysYtd'  => $leaveDaysYtd,
            'leaveTrend'    => $leaveTrend,
            'dtrToday'      => $dtrToday,
        ];
    }

    /* ── 2. IPCR Performance ──────────────────────────────────────────── */

    private function performance(?int $divisionId): array
    {
        $current  = IPCRRatingPeriod::current()->first();
        $staffIds = $this->staffIds($divisionId);

        $funnel = collect();
        $complianceByDivision = collect();
        if ($current) {
            $funnel = DB::table('employee_ipcrs')
                ->where('rating_period_id', $current->id)
                ->whereIn('user_id', $staffIds)
                ->selectRaw('status as label, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'label');

            $complianceByDivision = DB::table('divisions')
                ->where('divisions.status', 'active')
                ->leftJoin('users', function ($j) {
                    $j->on('users.division_id', '=', 'divisions.id')
                      ->where('users.status', '<>', 'inactive')
                      ->where(fn ($q) => $q->whereNull('users.emp_category')
                          ->orWhereNotIn('users.emp_category', ['student', 'parent']));
                })
                ->leftJoin('employee_ipcrs', function ($j) use ($current) {
                    $j->on('employee_ipcrs.user_id', '=', 'users.id')
                      ->where('employee_ipcrs.rating_period_id', $current->id);
                })
                ->selectRaw('divisions.division_name as label,
                             COUNT(DISTINCT users.id) as employees,
                             COUNT(DISTINCT employee_ipcrs.user_id) as submitted')
                ->groupBy('divisions.id', 'divisions.division_name')
                ->get()
                ->map(fn ($r) => [
                    'label'      => $r->label,
                    'employees'  => (int) $r->employees,
                    'submitted'  => (int) $r->submitted,
                    'rate'       => $r->employees ? round($r->submitted / $r->employees * 100) : 0,
                ]);
        }

        // Rating distribution for the most recent finalized IPCRs (last closed period)
        $lastClosed = IPCRRatingPeriod::where('status', 'closed')->orderByDesc('year')->orderByDesc('semester')->first();
        $ratingDist = collect();
        $avgRating  = null;
        if ($lastClosed) {
            $finals = DB::table('employee_ipcrs')
                ->where('rating_period_id', $lastClosed->id)
                ->whereIn('user_id', $staffIds)
                ->whereNotNull('final_numeric_rating')
                ->pluck('final_numeric_rating');
            $avgRating  = $finals->count() ? round($finals->avg(), 2) : null;
            $ratingDist = $finals->map(fn ($v) => $this->ipcr->adjectivalRating((float) $v))
                ->countBy()->sortDesc();
        }

        return [
            'currentPeriod'        => $current?->only('id', 'label'),
            'funnel'               => $funnel,
            'complianceByDivision' => $complianceByDivision,
            'lastClosedPeriod'     => $lastClosed?->only('id', 'label'),
            'avgRating'            => $avgRating,
            'avgAdjectival'        => $avgRating ? $this->ipcr->adjectivalRating($avgRating) : null,
            'ratingDistribution'   => $ratingDist,
        ];
    }

    /* ── 3. Requests & Service Delivery ───────────────────────────────── */

    private function requests(?int $divisionId): array
    {
        $staffIds = $divisionId ? $this->staffIds($divisionId) : null;
        $modules  = [];
        $trendTotals = array_fill(0, 6, 0);

        foreach (self::REQUEST_MODULES as $label => $cfg) {
            $terminal = array_merge($cfg['completed'], $cfg['declined']);

            $base = DB::table($cfg['table'])
                ->when($staffIds, fn ($q) => $q->whereIn($cfg['user'], $staffIds));

            $completedList = "'" . implode("','", array_map('addslashes', $cfg['completed'])) . "'";

            $row = (clone $base)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as this_month,
                SUM(CASE WHEN status IN ({$completedList}) THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status NOT IN ('" . implode("','", array_map('addslashes', $terminal)) . "') THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status NOT IN ('" . implode("','", array_map('addslashes', $terminal)) . "') AND created_at < ? THEN 1 ELSE 0 END) as open_over_7d
            ", [now()->startOfMonth(), now()->subDays(7)])->first();

            $modules[] = [
                'label'      => $label,
                'total'      => (int) $row->total,
                'thisMonth'  => (int) $row->this_month,
                'completed'  => (int) $row->completed,
                'open'       => (int) $row->open_count,
                'openOver7d' => (int) $row->open_over_7d,
                'completionRate' => $row->total ? round($row->completed / $row->total * 100) : 0,
            ];

            $trend = $this->monthlyTrend($cfg['table'], 'created_at', 6,
                fn ($q) => $staffIds ? $q->whereIn($cfg['user'], $staffIds) : $q);
            foreach ($trend as $i => $m) {
                $trendTotals[$i] += $m['total'];
            }
        }

        // Avg completion days for IT job requests (only module with completed_at)
        $avgCompletionDays = DB::table('it_job_requests')
            ->whereNotNull('completed_at')
            ->when($staffIds, fn ($q) => $q->whereIn('user_id', $staffIds))
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('AVG(DATEDIFF(completed_at, created_at)) as d')
            ->value('d');

        return [
            'modules'           => $modules,
            'totalOpen'         => array_sum(array_column($modules, 'open')),
            'openOver7d'        => array_sum(array_column($modules, 'openOver7d')),
            'trendLabels'       => $this->trendLabels(6),
            'trendTotals'       => $trendTotals,
            'avgCompletionDays' => $avgCompletionDays !== null ? round((float) $avgCompletionDays, 1) : null,
        ];
    }

    /* ── 4. Client Satisfaction (CSM) ─────────────────────────────────── */

    private function satisfaction(): array
    {
        $sqdCols = array_map(fn ($i) => "sqd{$i}", range(0, 8));

        // 6 = N/A on the SQD Likert scale — always excluded
        $dimensionAvgs = [];
        foreach ($sqdCols as $col) {
            $dimensionAvgs[$col] = round((float) DB::table('csm_responses')
                ->whereNotNull($col)->where($col, '<>', 6)->avg($col), 2);
        }

        $overall = collect($dimensionAvgs)->filter()->avg();
        $overall = $overall ? round($overall, 2) : null;

        $avgExprs = collect($sqdCols)
            ->map(fn ($c) => "AVG(CASE WHEN {$c} = 6 THEN NULL ELSE {$c} END)")
            ->implode(' + ');
        $nonNullCounts = collect($sqdCols)
            ->map(fn ($c) => "(AVG(CASE WHEN {$c} = 6 THEN NULL ELSE {$c} END) IS NOT NULL)")
            ->implode(' + ');

        $byOffice = DB::table('csm_responses')
            ->selectRaw("COALESCE(NULLIF(TRIM(office_availed), ''), 'Unspecified') as label,
                         COUNT(*) as responses,
                         ROUND(({$avgExprs}) / NULLIF(({$nonNullCounts}), 0), 2) as avg_score")
            ->groupBy('label')->orderByDesc('responses')->limit(10)->get();

        $trend = $this->monthlyTrend('csm_responses', 'created_at', 6);

        return [
            'responses'   => DB::table('csm_responses')->count(),
            'overall'     => $overall,
            'adjectival'  => $this->csmAdjectival($overall),
            'dimensions'  => $dimensionAvgs,
            'byOffice'    => $byOffice,
            'trendLabels' => $this->trendLabels(6),
            'trend'       => array_column($trend, 'total'),
        ];
    }

    private function csmAdjectival(?float $v): ?string
    {
        if ($v === null) return null;

        return match (true) {
            $v >= 4.5 => 'Excellent',
            $v >= 3.5 => 'Very Satisfactory',
            $v >= 2.5 => 'Satisfactory',
            $v >= 1.5 => 'Fair',
            default   => 'Poor',
        };
    }

    /* ── 5. Academics ─────────────────────────────────────────────────── */

    private function academics(): array
    {
        $enrollment = DB::table('student_enrollments')
            ->where('status', 'enrolled')
            ->selectRaw('grade_level as label, COUNT(*) as total')
            ->groupBy('grade_level')->orderBy('grade_level')->get();

        $enrollmentByStatus = DB::table('student_enrollments')
            ->selectRaw('status as label, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'label');

        $loadDist = DB::table('faculty_loads')
            ->join('school_years', 'school_years.id', '=', 'faculty_loads.school_year_id')
            ->where('school_years.is_current', true)
            ->selectRaw('load_status as label, COUNT(*) as total')
            ->groupBy('load_status')->pluck('total', 'label');

        $classRecords = DB::table('class_records')
            ->join('school_years', 'school_years.id', '=', 'class_records.school_year_id')
            ->where('school_years.is_current', true)
            ->selectRaw('class_records.status as label, COUNT(*) as total')
            ->groupBy('class_records.status')->pluck('total', 'label');

        $gateToday = DB::table('student_attendance_logs')
            ->whereDate('scan_time', today())
            ->selectRaw('type as label, COUNT(*) as total')
            ->groupBy('type')->pluck('total', 'label');

        return [
            'enrollment'         => $enrollment,
            'enrollmentByStatus' => $enrollmentByStatus,
            'enrolledTotal'      => (int) $enrollment->sum('total'),
            'facultyLoad'        => $loadDist,
            'classRecords'       => $classRecords,
            'gateToday'          => $gateToday,
        ];
    }

    /* ── 6. Recruitment ───────────────────────────────────────────────── */

    private function recruitment(): array
    {
        return [
            'openVacancies' => DB::table('job_vacancies')->where('status', 'open')->count(),
            'pipeline'      => DB::table('applications')->whereNull('deleted_at')
                ->selectRaw('current_stage as label, COUNT(*) as total')
                ->groupBy('current_stage')->pluck('total', 'label'),
            'applicationsThisMonth' => DB::table('applications')->whereNull('deleted_at')
                ->where('created_at', '>=', now()->startOfMonth())->count(),
            'placementsPending' => DB::table('placements')->where('status', 'pending')->count(),
        ];
    }

    /* ── 7. Finance snapshot ──────────────────────────────────────────── */

    private function finance(): array
    {
        $latestRun = DB::table('payroll_runs')->whereNull('deleted_at')
            ->orderByDesc('year')->orderByDesc('month')
            ->first(['year', 'month', 'period', 'status', 'employee_count', 'total_net', 'pay_date']);

        $netTrend = DB::table('payroll_runs')->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw('year, month, SUM(total_net) as total')
            ->groupBy('year', 'month')->orderBy('year')->orderBy('month')->get();

        $prByStatus = $this->rescue('finance.pr', fn () => DB::table('purchase_requests')
            ->selectRaw('status as label, COUNT(*) as total')->groupBy('status')->pluck('total', 'label'), collect());

        $dvByStatus = $this->rescue('finance.dv', fn () => DB::table('disbursement_vouchers')
            ->selectRaw('status as label, COUNT(*) as total')->groupBy('status')->pluck('total', 'label'), collect());

        return [
            'latestRun'  => $latestRun,
            'netTrend'   => $netTrend,
            'prByStatus' => $prByStatus,
            'dvByStatus' => $dvByStatus,
        ];
    }

    /* ── 8. Operations pulse ──────────────────────────────────────────── */

    private function operations(?int $divisionId): array
    {
        $staffIds = $divisionId ? $this->staffIds($divisionId) : null;

        $routings = DB::table('document_routings')
            ->when($staffIds, fn ($q) => $q->whereIn('receiver_id', $staffIds))
            ->selectRaw("
                SUM(CASE WHEN status IN ('Pending','Queued','Forwarded','Received') THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status IN ('Pending','Queued','Forwarded','Received') AND due_at IS NOT NULL AND due_at < NOW() THEN 1 ELSE 0 END) as overdue
            ")->first();

        $issuancesThisMonth = DB::table('issuances')
            ->where('created_at', '>=', now()->startOfMonth())->count();

        $openErrors = DB::table('error_reports')->where('status', 'open')->count();

        $currentPeriodId = IPCRRatingPeriod::current()->value('id');
        $tasks = DB::table('committee_tasks')
            ->when($currentPeriodId, fn ($q) => $q->where(fn ($qq) =>
                $qq->whereNull('rating_period_id')->orWhere('rating_period_id', $currentPeriodId)))
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN status = 'stuck' THEN 1 ELSE 0 END) as stuck")
            ->first();

        return [
            'routingsOpen'       => (int) ($routings->open_count ?? 0),
            'routingsOverdue'    => (int) ($routings->overdue ?? 0),
            'issuancesThisMonth' => $issuancesThisMonth,
            'openErrorReports'   => $openErrors,
            'committeeTasks'     => [
                'total' => (int) ($tasks->total ?? 0),
                'done'  => (int) ($tasks->done ?? 0),
                'stuck' => (int) ($tasks->stuck ?? 0),
            ],
            'activeUsersNow'     => $this->activeUsersNow(),
        ];
    }

    /** Watchtower Redis zset — 30s cache; 0 when Redis is unavailable (dev). */
    private function activeUsersNow(): int
    {
        return (int) Cache::remember('exec_dashboard.active_users', 30, function () {
            try {
                return count(Redis::zrangebyscore('atlas:active_users', now()->subMinutes(30)->timestamp, '+inf'));
            } catch (\Throwable) {
                return 0;
            }
        });
    }

    /* ── Attention flags ──────────────────────────────────────────────── */

    private function attention(?int $divisionId, array $sections): array
    {
        $data  = [];
        $push = function (string $severity, string $label, int $count, string $routeName) use (&$data) {
            if ($count > 0) {
                $data[] = ['severity' => $severity, 'label' => $label, 'count' => $count, 'route' => $routeName];
            }
        };

        $ops = $sections['operations'] ?? [];
        $push('danger', 'Overdue document routings', $ops['routingsOverdue'] ?? 0, 'document-tracking.index');
        $push('warning', 'Stuck committee tasks', $ops['committeeTasks']['stuck'] ?? 0, 'pm-committees.index');
        $push('warning', 'Open error reports', $ops['openErrorReports'] ?? 0, 'error-reports.index');

        $req = $sections['requests'] ?? [];
        $push('danger', 'Requests open beyond 7 days', $req['openOver7d'] ?? 0, 'dashboard');

        $staffIds = $this->staffIds($divisionId);
        $leaveStale = DB::table('leave_applications')->whereNull('deleted_at')
            ->whereIn('user_id', $staffIds)
            ->whereIn('status', ['pending', 'forwarded', 'hr_verified'])
            ->where('created_at', '<', now()->subDays(5))
            ->count();
        $push('warning', 'Leave applications pending over 5 days', $leaveStale, 'hr.leave.index');

        $current = IPCRRatingPeriod::current()->first();
        if ($current && $current->isOpen()) {
            $submitted = DB::table('employee_ipcrs')->where('rating_period_id', $current->id)
                ->whereIn('user_id', $staffIds)->distinct()->count('user_id');
            $push('warning', "Employees without an IPCR for {$current->label}", max(0, $staffIds->count() - $submitted), 'employee-ipcr.index');
        }

        return $data;
    }

    /* ── Division scorecard (campus lens only) ────────────────────────── */

    private function scorecard(): array
    {
        $currentPeriodId = IPCRRatingPeriod::current()->value('id');

        return DB::table('divisions')->where('status', 'active')->get(['id', 'division_name'])
            ->map(function ($division) use ($currentPeriodId) {
                $staffIds = $this->staffIds($division->id);
                $headcount = $staffIds->count();

                $leaveDays = (float) DB::table('leave_applications')->whereNull('deleted_at')
                    ->whereIn('user_id', $staffIds)->where('status', 'approved')
                    ->whereYear('date_from', now()->year)->sum('days_applied');

                $ipcrSubmitted = $currentPeriodId ? DB::table('employee_ipcrs')
                    ->where('rating_period_id', $currentPeriodId)
                    ->whereIn('user_id', $staffIds)->distinct()->count('user_id') : 0;

                [$reqTotal, $reqCompleted] = $this->requestCompletionFor($staffIds);

                return [
                    'division'        => $division->division_name,
                    'headcount'       => $headcount,
                    'leaveDaysPerEmp' => $headcount ? round($leaveDays / $headcount, 1) : 0,
                    'ipcrRate'        => $headcount ? round($ipcrSubmitted / $headcount * 100) : 0,
                    'requestTotal'    => $reqTotal,
                    'requestRate'     => $reqTotal ? round($reqCompleted / $reqTotal * 100) : null,
                ];
            })
            ->sortByDesc('headcount')->values()->all();
    }

    private function requestCompletionFor($staffIds): array
    {
        $total = 0;
        $completed = 0;
        foreach (self::REQUEST_MODULES as $cfg) {
            $completedList = "'" . implode("','", array_map('addslashes', $cfg['completed'])) . "'";
            $row = DB::table($cfg['table'])->whereIn($cfg['user'], $staffIds)
                ->selectRaw("COUNT(*) as t, SUM(CASE WHEN status IN ({$completedList}) THEN 1 ELSE 0 END) as c")
                ->first();
            $total     += (int) $row->t;
            $completed += (int) $row->c;
        }

        return [$total, $completed];
    }

    /* ── Trend helpers ────────────────────────────────────────────────── */

    private function trendLabels(int $months): array
    {
        return collect(range($months - 1, 0))
            ->map(fn ($i) => now()->subMonths($i)->format('M Y'))
            ->all();
    }

    /** @return array<int, array{label: string, total: int}> oldest → newest */
    private function monthlyTrend(string $table, string $dateCol, int $months, ?callable $scope = null): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = DB::table($table)
            ->when($scope, fn ($q) => $scope($q))
            ->where($dateCol, '>=', $start)
            ->selectRaw("DATE_FORMAT({$dateCol}, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')->pluck('total', 'ym');

        return collect(range($months - 1, 0))->map(function ($i) use ($rows) {
            $m = now()->subMonths($i);

            return ['label' => $m->format('M Y'), 'total' => (int) ($rows[$m->format('Y-m')] ?? 0)];
        })->all();
    }
}
