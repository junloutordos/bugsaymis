<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\HR\DtrRecord;
use App\Models\HR\LeaveApplication;
use App\Models\IPCRRatingPeriod;
use App\Models\JobItem;
use App\Models\JobVacancy;
use App\Models\LearningProgram;
use App\Models\Placement;
use App\Models\Reward;
use App\Models\RewardNomination;
use App\Models\SALN\SalnRecord;
use App\Models\TrainingNeed;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HRDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('HR/Dashboard', [
            'hr'          => $user->hasPermission('hr.employees.manage') ? $this->hrCoreSection($request) : null,
            'recruitment' => $user->hasPermission('recruitment.view')    ? $this->recruitmentSection($request) : null,
            'pms'         => $user->hasPermission('ipcr.monitor')       ? $this->pmsSection($request) : null,
            'lnd'         => $user->hasPermission('lnd.view')           ? $this->lndSection($request) : null,
            'saln'        => $user->hasPermission('saln.view_all')      ? $this->salnSection($request) : null,
            'rewards'     => $user->hasPermission('rewards.view')       ? $this->rewardsSection($request) : null,
        ]);
    }

    // ── 1. HR Core ───────────────────────────────────────────────────────────────

    private function hrCoreSection(Request $request): array
    {
        $month = $request->input('hr_month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);

        $activeEmployees = fn () => User::where('status', '!=', 'inactive')
            ->whereNotNull('emp_category')
            ->where('emp_category', '!=', '');

        // Deduplicate by name — one physical person may have multiple accounts
        $distinctName = DB::raw('DISTINCT LOWER(TRIM(name))');

        $categoryBreakdown = $activeEmployees()
            ->select('emp_category', DB::raw('COUNT(DISTINCT LOWER(TRIM(name))) as total'))
            ->groupBy('emp_category')
            ->pluck('total', 'emp_category');

        $attendance = DtrRecord::selectRaw('
                SUM(CASE WHEN attendance_status = "present"  THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN attendance_status = "absent"   THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN attendance_status = "on_leave" THEN 1 ELSE 0 END) as on_leave_count,
                COUNT(*) as total_count
            ')
            ->whereYear('work_date', $y)
            ->whereMonth('work_date', $m)
            ->first();

        $attendanceRate = $attendance->total_count > 0
            ? round($attendance->present_count / $attendance->total_count * 100, 1)
            : null;

        $attendanceTrend = collect(range(5, 0))->map(function ($i) {
            $d   = Carbon::now()->subMonths($i);
            $row = DtrRecord::selectRaw('
                    SUM(CASE WHEN attendance_status = "present"  THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN attendance_status = "absent"   THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN attendance_status = "on_leave" THEN 1 ELSE 0 END) as on_leave_count
                ')
                ->whereYear('work_date', $d->year)
                ->whereMonth('work_date', $d->month)
                ->first();

            return [
                'month'    => $d->format('M Y'),
                'present'  => (int) ($row->present_count ?? 0),
                'absent'   => (int) ($row->absent_count ?? 0),
                'on_leave' => (int) ($row->on_leave_count ?? 0),
            ];
        })->values();

        $leaveTypeBreakdown = LeaveApplication::whereYear('date_from', $y)
            ->select('leave_type_id', DB::raw('count(*) as total'), DB::raw('sum(days_applied) as days'))
            ->with('leaveType:id,name')
            ->groupBy('leave_type_id')
            ->get()
            ->map(fn ($r) => [
                'name'  => $r->leaveType?->name ?? 'Unspecified',
                'total' => (int) $r->total,
                'days'  => (float) $r->days,
            ]);

        $today = now()->toDateString();

        $onLeaveToday = LeaveApplication::where('status', 'approved')
            ->whereDate('date_from', '<=', $today)
            ->whereDate('date_to', '>=', $today)
            ->with('user:id,name', 'leaveType:id,name')
            ->get()
            ->filter(fn ($la) => $la->user !== null)
            ->groupBy(fn ($la) => strtolower(trim($la->user->name)))
            ->map(fn ($group) => $group->first())
            ->map(fn ($la) => [
                'name'       => $la->user->name,
                'leave_type' => $la->leaveType?->name ?? 'Leave',
                'date_from'  => $la->date_from,
                'date_to'    => $la->date_to,
            ])
            ->values();

        $onGatepassToday = DB::table('gatepass')
            ->leftJoin('users', DB::raw('CAST(users.badge_id AS CHAR)'), '=', DB::raw('CAST(gatepass.badgeNumber AS CHAR)'))
            ->where('gatepass.gatepass_date', 'LIKE', $today . '%')
            ->whereIn('gatepass.status', ['Approved', 'OCD Approved'])
            ->whereNotNull('users.name')
            ->select('users.name', 'gatepass.gatepass_type', 'gatepass.purpose', 'gatepass.gatepass_timeout', 'gatepass.gatepass_timein')
            ->get()
            ->groupBy(fn ($gp) => strtolower(trim($gp->name)))
            ->map(fn ($group) => $group->first())
            ->map(fn ($gp) => [
                'name'     => $gp->name,
                'type'     => $gp->gatepass_type,
                'purpose'  => $gp->purpose,
                'timeout'  => $gp->gatepass_timeout,
                'timein'   => $gp->gatepass_timein,
            ])
            ->values();

        return [
            'month'              => $month,
            'kpi'                => [
                'total_active_employees' => $activeEmployees()->count($distinctName),
                'attendance_rate'         => $attendanceRate,
                'pending_leave'           => LeaveApplication::whereNotIn('status', ['approved', 'rejected', 'cancelled'])->count(),
                'pending_gate_pass'       => DB::table('gatepass')->where('status', 'Pending')->count(),
            ],
            'categoryBreakdown'  => $categoryBreakdown,
            'attendanceTrend'    => $attendanceTrend,
            'leaveTypeBreakdown' => $leaveTypeBreakdown,
            'onLeaveToday'       => $onLeaveToday,
            'onGatepassToday'    => $onGatepassToday,
        ];
    }

    // ── 2. Recruitment ───────────────────────────────────────────────────────────

    private function recruitmentSection(Request $request): array
    {
        $year = (int) $request->input('recruitment_year', now()->year);

        $jobItemsByStatus = JobItem::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $pendingOnboarding = Placement::where('status', 'pending')
            ->whereHas('onboardingTasks', fn ($q) => $q->where('status', 'pending'))
            ->count();

        $stuckApplications = Application::whereNotIn('current_stage', ['placement', 'rejected', 'withdrawn'])
            ->where('updated_at', '<=', now()->subDays(14))
            ->select('current_stage', DB::raw('count(*) as total'))
            ->groupBy('current_stage')
            ->get()
            ->map(fn ($r) => ['stage' => $r->current_stage, 'total' => (int) $r->total]);

        $monthlyPipeline = DB::table('applications')
            ->select(
                DB::raw('MONTH(application_date) as month'),
                DB::raw('COUNT(*) as submitted'),
                DB::raw("SUM(CASE WHEN current_stage = 'placement' THEN 1 ELSE 0 END) as placed"),
                DB::raw("SUM(CASE WHEN current_stage = 'rejected'  THEN 1 ELSE 0 END) as rejected")
            )
            ->whereYear('application_date', $year)
            ->whereNull('deleted_at')
            ->groupBy(DB::raw('MONTH(application_date)'))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $pipeline = collect(range(1, 12))->map(fn ($m) => [
            'month'     => date('M', mktime(0, 0, 0, $m, 1)),
            'submitted' => (int) ($monthlyPipeline[$m]->submitted ?? 0),
            'placed'    => (int) ($monthlyPipeline[$m]->placed    ?? 0),
            'rejected'  => (int) ($monthlyPipeline[$m]->rejected  ?? 0),
        ]);

        return [
            'year' => $year,
            'kpi'  => [
                'open_positions'     => ($jobItemsByStatus['approved'] ?? 0) + ($jobItemsByStatus['published'] ?? 0),
                'active_vacancies'   => JobVacancy::where('status', 'open')->count(),
                'applications_year'  => Application::whereYear('application_date', $year)->count(),
                'placements_year'    => Placement::whereYear('created_at', $year)->count(),
                'pending_onboarding' => $pendingOnboarding,
            ],
            'pipeline'          => $pipeline,
            'stuckApplications' => $stuckApplications,
        ];
    }

    // ── 3. Performance Management (IPCR/PMS) ────────────────────────────────────

    private function pmsSection(Request $request): array
    {
        $period = $request->input('pms_period', IPCRRatingPeriod::active()->value('label'));

        $statusFunnel = EmployeeIPCR::where('rating_period', $period)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $rated = EmployeeIPCR::where('rating_period', $period)
            ->whereIn('status', ['Approved by PMT', 'Director Signed'])
            ->with('plans.performance_indicator.agencyOutcome', 'user:id,division_id')
            ->get();

        $ratings           = $rated->map(fn ($i) => $this->computeWeightedAverage($i->plans))->filter();
        $ratingDistribution = $ratings->map(fn ($r) => $this->adjectivalRating($r))->countBy();

        $divisionTotals = User::where('status', '!=', 'inactive')
            ->whereNotNull('emp_category')
            ->select('division_id', DB::raw('COUNT(DISTINCT LOWER(TRIM(name))) as total'))
            ->groupBy('division_id')
            ->pluck('total', 'division_id');

        $divisionSubmitted = $rated->groupBy(fn ($i) => $i->user?->division_id)->map(fn ($g) => $g->count());

        $completionByDivision = Division::where('status', 'active')->get(['id', 'division_name'])
            ->map(function ($d) use ($divisionTotals, $divisionSubmitted) {
                $total     = (int) ($divisionTotals[$d->id] ?? 0);
                $submitted = (int) ($divisionSubmitted[$d->id] ?? 0);

                return [
                    'division'  => $d->division_name,
                    'submitted' => $submitted,
                    'total'     => $total,
                    'rate'      => $total > 0 ? round($submitted / $total * 100, 1) : 0,
                ];
            })
            ->sortByDesc('rate')
            ->values();

        return [
            'period'              => $period,
            'periods'             => IPCRRatingPeriod::active()->pluck('label'),
            'kpi'                 => [
                'total_ipcrs'             => EmployeeIPCR::where('rating_period', $period)->count(),
                'pending_director_signoff'=> (int) ($statusFunnel['Approved by PMT'] ?? 0),
                'average_rating'          => $ratings->isNotEmpty() ? round($ratings->avg(), 2) : null,
            ],
            'statusFunnel'         => $statusFunnel,
            'ratingDistribution'   => $ratingDistribution,
            'completionByDivision' => $completionByDivision,
        ];
    }

    /**
     * Mirrors PMTIPCRController::computeWeightedAverage() (Strategic 30% / Core 55% / Support 15%).
     */
    private function computeWeightedAverage($plans): ?float
    {
        $weights = ['Strategic Functions' => 0.30, 'Core Functions' => 0.55, 'Support Functions' => 0.15];
        $groups  = [];

        foreach ($plans as $plan) {
            $ft = $this->normalizeFunctionType($plan->performance_indicator?->agencyOutcome?->function_type);
            $supAvg = $plan->pivot?->sup_average;
            if ($supAvg === null || $supAvg === '') {
                continue;
            }
            $groups[$ft]['total'] = ($groups[$ft]['total'] ?? 0) + (float) $supAvg;
            $groups[$ft]['count'] = ($groups[$ft]['count'] ?? 0) + 1;
        }

        $totalWeighted = 0;
        $hasAny        = false;
        foreach ($groups as $ft => $data) {
            $weight         = $weights[$ft] ?? 0;
            $totalWeighted += ($data['total'] / $data['count']) * $weight;
            $hasAny         = true;
        }

        return $hasAny ? round($totalWeighted, 2) : null;
    }

    private function normalizeFunctionType(?string $raw): string
    {
        if (! $raw) {
            return 'Uncategorized';
        }
        $t = strtolower(trim($raw));
        if (in_array($t, ['strategic', 'strategic functions', 'strategic function'])) return 'Strategic Functions';
        if (in_array($t, ['core', 'core functions', 'core function']))               return 'Core Functions';
        if (in_array($t, ['support', 'support functions', 'support function']))      return 'Support Functions';

        return trim($raw);
    }

    private function adjectivalRating(float $score): string
    {
        return match (true) {
            $score >= 5.0 => 'Outstanding',
            $score >= 4.0 => 'Very Satisfactory',
            $score >= 3.0 => 'Satisfactory',
            $score >= 2.0 => 'Unsatisfactory',
            default       => 'Poor',
        };
    }

    // ── 4. Learning & Development ───────────────────────────────────────────────

    private function lndSection(Request $request): array
    {
        $year = (int) $request->input('lnd_year', now()->year);

        $participantsTrained = TrainingParticipant::whereHas('session', fn ($q) => $q->whereYear('session_date', $year))
            ->where('completion_status', 'completed')
            ->select('employee_id')
            ->distinct()
            ->count('employee_id');

        $sessionsPerMonth = collect(range(1, 12))->map(fn ($m) => [
            'month' => Carbon::create($year, $m, 1)->format('M'),
            'count' => TrainingSession::whereYear('session_date', $year)->whereMonth('session_date', $m)->count(),
        ]);

        return [
            'year' => $year,
            'kpi'  => [
                'active_programs'      => LearningProgram::active()->count(),
                'sessions_year'        => TrainingSession::whereYear('session_date', $year)->count(),
                'upcoming_sessions'    => TrainingSession::upcoming()->count(),
                'participants_trained' => $participantsTrained,
                'pending_tna'          => TrainingNeed::pending()->count(),
            ],
            'sessionsPerMonth' => $sessionsPerMonth,
        ];
    }

    // ── 5. SALN ──────────────────────────────────────────────────────────────────

    private function salnSection(Request $request): array
    {
        $year = (int) $request->input('saln_year', now()->year);

        $totalEmployees = User::whereHas('roles', fn ($q) => $q->whereNotIn('name', ['Student', 'Parent']))
            ->where('status', '!=', 'inactive')
            ->count(DB::raw('DISTINCT LOWER(TRIM(name))'));

        $statusCounts = SalnRecord::where('year', $year)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $filed    = $statusCounts['filed']      ?? 0;
        $approved = $statusCounts['approved']   ?? 0;
        $review   = ($statusCounts['submitted'] ?? 0) + ($statusCounts['under_review'] ?? 0);
        $draft    = ($statusCounts['draft']     ?? 0) + ($statusCounts['returned']     ?? 0);
        $filers   = (int) $statusCounts->sum();

        return [
            'year'    => $year,
            'kpi'     => [
                'total_required' => $totalEmployees,
                'filed'          => (int) $filed,
                'approved'       => (int) $approved,
                'review'         => (int) $review,
                'draft'          => (int) $draft,
                'non_filers'     => max(0, $totalEmployees - $filers),
            ],
            'statusCounts' => $statusCounts,
        ];
    }

    // ── 6. Rewards & Recognition ─────────────────────────────────────────────────

    private function rewardsSection(Request $request): array
    {
        $year = (int) $request->input('rewards_year', now()->year);

        $byType = RewardNomination::whereYear('created_at', $year)
            ->select('reward_type_id', DB::raw('count(*) as total'))
            ->with('rewardType:id,name')
            ->groupBy('reward_type_id')
            ->get()
            ->map(fn ($r) => ['name' => $r->rewardType?->name ?? 'Unspecified', 'total' => (int) $r->total]);

        $awardsByIncentive = Reward::select('incentive_type', DB::raw('count(*) as count'), DB::raw('sum(incentive_value) as total_value'))
            ->whereHas('nomination', fn ($q) => $q->whereYear('created_at', $year))
            ->groupBy('incentive_type')
            ->get();

        return [
            'year' => $year,
            'kpi'  => [
                'total_nominations' => RewardNomination::whereYear('created_at', $year)->count(),
                'pending'           => RewardNomination::whereYear('created_at', $year)
                                            ->whereIn('status', ['pending', 'screened', 'evaluated'])->count(),
                'awarded'           => Reward::whereHas('nomination', fn ($q) => $q->whereYear('created_at', $year))->count(),
                'total_value'       => (float) $awardsByIncentive->sum('total_value'),
            ],
            'byType'            => $byType,
            'awardsByIncentive' => $awardsByIncentive,
        ];
    }
}
