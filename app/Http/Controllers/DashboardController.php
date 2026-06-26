<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Role;
use App\Models\User;
use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\ITJobRequest;
use App\Models\VehicleRequest;
use App\Models\FacilityRequest;
use App\Models\ServiceRequest;
use App\Models\WorkRequest;
use App\Models\MessengerialRequest;
use App\Models\Consultation;
use App\Models\LearningProgram;
use App\Models\TrainingSession;
use App\Models\TrainingNeed;
use App\Models\IndividualDevelopmentPlan;
use App\Models\RewardNomination;
use App\Models\Reward;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Scholars Count
        |--------------------------------------------------------------------------
        */
        $scholarsCount = 0;
        try {
            $columns = collect(DB::select("SHOW COLUMNS FROM students"))
                ->pluck('Field')
                ->toArray();

            $statusCandidates = [
                'status', 'student_status', 'enrollment_status',
                'enrolled', 'enrollment', 'status_desc',
            ];
            $statusField = collect($statusCandidates)->first(fn ($f) => in_array($f, $columns));

            $scholarsCount = $statusField
                ? DB::table('students')->whereRaw("LOWER(TRIM(`{$statusField}`)) = ?", ['enrolled'])->count()
                : DB::table('students')->count();
        } catch (\Throwable $e) {
            logger()->warning('Scholars count error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Employee Counts (Faculty, Staff, Total)
        |--------------------------------------------------------------------------
        */
        $totalEmployees      = 0;
        $facultyCount        = 0;
        $staffCount          = 0;
        $activeDivisions     = 0;
        $employeeMaleCount   = 0;
        $employeeFemaleCount = 0;
        $facultyMaleCount    = 0;
        $facultyFemaleCount  = 0;
        $staffMaleCount      = 0;
        $staffFemaleCount    = 0;
        $employeesByDivision = [];

        try {
            // Base scope: active employees with an assigned employee category
            $activeEmployeeBase = fn () => User::where('status', '!=', 'inactive')
                ->whereNotNull('emp_category')
                ->where('emp_category', '!=', '');

            // Deduplicate by name — one physical person may have multiple accounts
            $dn = DB::raw('DISTINCT LOWER(TRIM(name))');

            $totalEmployees = $activeEmployeeBase()->count($dn);

            $facultyCount = $activeEmployeeBase()
                ->whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
                ->count($dn);

            $staffCount = $activeEmployeeBase()
                ->whereHas('roles', fn ($q) => $q->where('roles.name', 'Staff'))
                ->count($dn);

            $activeDivisions     = Division::where('status', 'active')->count();
            $employeeMaleCount   = $activeEmployeeBase()->whereRaw("LOWER(TRIM(COALESCE(sex,''))) IN ('male','m')")->count($dn);
            $employeeFemaleCount = $activeEmployeeBase()->whereRaw("LOWER(TRIM(COALESCE(sex,''))) IN ('female','f')")->count($dn);

            $facultyBase = fn () => $activeEmployeeBase()->whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'));
            $staffBase   = fn () => $activeEmployeeBase()->whereHas('roles', fn ($q) => $q->where('roles.name', 'Staff'));

            $facultyMaleCount   = $facultyBase()->whereRaw("LOWER(TRIM(COALESCE(sex,''))) IN ('male','m')")->count($dn);
            $facultyFemaleCount = $facultyBase()->whereRaw("LOWER(TRIM(COALESCE(sex,''))) IN ('female','f')")->count($dn);
            $staffMaleCount     = $staffBase()->whereRaw("LOWER(TRIM(COALESCE(sex,''))) IN ('male','m')")->count($dn);
            $staffFemaleCount   = $staffBase()->whereRaw("LOWER(TRIM(COALESCE(sex,''))) IN ('female','f')")->count($dn);

            $divRows = DB::table('users')
                ->join('divisions', 'users.division_id', '=', 'divisions.id')
                ->select(
                    DB::raw("COALESCE(NULLIF(TRIM(divisions.acronym),''), divisions.division_name) as division"),
                    DB::raw('COUNT(DISTINCT LOWER(TRIM(users.name))) as cnt')
                )
                ->where('users.status', '!=', 'inactive')
                ->whereNotNull('users.emp_category')
                ->where('users.emp_category', '!=', '')
                ->groupBy('divisions.id', 'division')
                ->orderByDesc('cnt')
                ->take(10)
                ->get();

            $employeesByDivision = $divRows->map(fn ($d) => [
                'division' => $d->division,
                'count'    => (int) $d->cnt,
            ])->values()->toArray();
        } catch (\Throwable $e) {
            logger()->warning('Employee analytics error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | IPCR Analytics
        |--------------------------------------------------------------------------
        */
        $ipcrByStatus  = [];
        $ipcrForReview = 0;
        $recentIPCRs   = [];

        try {
            $ipcrRows = EmployeeIPCR::select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->get();

            $ipcrByStatus  = $ipcrRows->map(fn ($r) => ['status' => $r->status, 'total' => (int) $r->total])->values()->toArray();
            $ipcrForReview = (int) ($ipcrRows->firstWhere('status', 'For Review')?->total ?? 0);

            $recentIPCRs = EmployeeIPCR::with('user:id,name')
                ->latest()
                ->take(6)
                ->get()
                ->map(fn ($i) => [
                    'id'            => $i->id,
                    'user'          => $i->user?->name ?? 'Unknown',
                    'rating_period' => $i->rating_period,
                    'status'        => $i->status,
                    'updated_at'    => $i->updated_at?->diffForHumans() ?? '',
                ])
                ->toArray();
        } catch (\Throwable $e) {
            logger()->warning('IPCR analytics error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | IT Job Requests by Category
        |--------------------------------------------------------------------------
        */
        $itjrByCategory = [];
        try {
            $itjrByCategory = ITJobRequest::select('category', DB::raw('COUNT(*) as total'))
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->groupBy('category')
                ->get()
                ->map(fn ($r) => ['category' => $r->category, 'total' => (int) $r->total])
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            logger()->warning('ITJR by category error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Request Overview (Active / Pending Counts per Module)
        |--------------------------------------------------------------------------
        */
        $terminalStatuses     = ['Completed', 'Declined', 'Rejected', 'Cancelled'];
        $requestOverview      = [];
        $totalPendingRequests = 0;

        try {
            $requestOverview = [
                [
                    'label'     => 'IT Job Requests',
                    'pending'   => ITJobRequest::where('status', 'In Progress')->count(),
                    'completed' => ITJobRequest::whereIn('status', ['Acted by MIS', 'Request Completed'])->count(),
                    'total'     => ITJobRequest::count(),
                ],
                [
                    'label'     => 'Vehicle Requests',
                    'pending'   => VehicleRequest::where('status', 'Pending')->count(),
                    'completed' => VehicleRequest::where('status', 'OCD Approved')->count(),
                    'total'     => VehicleRequest::count(),
                ],
                [
                    'label'     => 'Facility Requests',
                    'pending'   => FacilityRequest::where('status', 'Pending')->count(),
                    'completed' => FacilityRequest::where('status', 'FAD Approved')->count(),
                    'total'     => FacilityRequest::count(),
                ],
                [
                    'label'     => 'Service Requests',
                    'pending'   => ServiceRequest::where('status', 'Pending')->count(),
                    'completed' => ServiceRequest::where('status', 'FAD Approved')->count(),
                    'total'     => ServiceRequest::count(),
                ],
                [
                    'label'     => 'Work Requests',
                    'pending'   => WorkRequest::whereIn('status', ['Pending', 'GSU Approved', 'FAD Approved'])->count(),
                    'completed' => WorkRequest::where('status', 'Completed')->count(),
                    'total'     => WorkRequest::count(),
                ],
                [
                    'label'     => 'Messengerial',
                    'pending'   => MessengerialRequest::where('status', 'Pending')->count(),
                    'completed' => MessengerialRequest::where('status', 'Completed')->count(),
                    'total'     => MessengerialRequest::count(),
                ],
            ];
            $totalPendingRequests = (int) collect($requestOverview)->sum('pending');
        } catch (\Throwable $e) {
            logger()->warning('Request overview error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Monthly Request Trends (Last 6 Months)
        |--------------------------------------------------------------------------
        */
        $monthlyTrends = ['labels' => [], 'datasets' => []];
        try {
            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $months[] = Carbon::now()->startOfMonth()->subMonths($i);
            }

            $monthLabels = array_map(fn ($m) => $m->format('M Y'), $months);
            $colors      = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];

            $modules = [
                ['label' => 'IT Job Requests',   'model' => ITJobRequest::class],
                ['label' => 'Vehicle Requests',  'model' => VehicleRequest::class],
                ['label' => 'Facility Requests', 'model' => FacilityRequest::class],
                ['label' => 'Service Requests',  'model' => ServiceRequest::class],
                ['label' => 'Work Requests',     'model' => WorkRequest::class],
            ];

            $datasets = [];
            foreach ($modules as $ci => $module) {
                $data = array_map(
                    fn ($m) => $module['model']::whereMonth('created_at', $m->month)
                        ->whereYear('created_at', $m->year)
                        ->count(),
                    $months
                );
                $datasets[] = [
                    'label' => $module['label'],
                    'data'  => $data,
                    'color' => $colors[$ci],
                ];
            }

            $monthlyTrends = ['labels' => $monthLabels, 'datasets' => $datasets];
        } catch (\Throwable $e) {
            logger()->warning('Monthly trends error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | L&D Analytics
        |--------------------------------------------------------------------------
        */
        $lndStats = ['programs' => 0, 'sessions' => 0, 'tna_pending' => 0, 'idp_pending' => 0];
        try {
            $lndStats = [
                'programs'    => LearningProgram::count(),
                'sessions'    => TrainingSession::count(),
                'tna_pending' => TrainingNeed::where('status', 'pending')->count(),
                'idp_pending' => IndividualDevelopmentPlan::where('status', 'submitted')->count(),
            ];
        } catch (\Throwable $e) {
            logger()->warning('L&D analytics error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Rewards & Recognition Analytics
        |--------------------------------------------------------------------------
        */
        $rewardsStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'awarded_this_year' => 0];
        try {
            $rewardsStats = [
                'total'             => RewardNomination::count(),
                'pending'           => RewardNomination::where('status', 'pending')->count(),
                'approved'          => RewardNomination::where('status', 'approved')->count(),
                'awarded_this_year' => Reward::whereYear('award_date', now()->year)->count(),
            ];
        } catch (\Throwable $e) {
            logger()->warning('Rewards analytics error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Student Gender Count
        |--------------------------------------------------------------------------
        */
        $maleCount   = 0;
        $femaleCount = 0;
        try {
            $tables      = ['students', 'student'];
            $genderField = null;
            $tableUsed   = null;
            $columns     = [];

            foreach ($tables as $table) {
                try {
                    $columns = collect(DB::select("SHOW COLUMNS FROM {$table}"))->pluck('Field')->toArray();
                    foreach (['sex', 'gender', 'gender_identity', 'sex_at_birth'] as $g) {
                        if (in_array($g, $columns)) { $genderField = $g; $tableUsed = $table; break 2; }
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            if ($genderField && $tableUsed) {
                $statusCandidates = ['status', 'student_status', 'enrollment_status', 'enrolled', 'enrollment', 'status_desc'];
                $statusField      = collect($statusCandidates)->first(fn ($f) => in_array($f, $columns));

                $baseMale   = DB::table($tableUsed)->whereRaw("LOWER(TRIM(`{$genderField}`)) IN ('male','m')");
                $baseFemale = DB::table($tableUsed)->whereRaw("LOWER(TRIM(`{$genderField}`)) IN ('female','f')");

                if ($statusField) {
                    $baseMale   = $baseMale->whereRaw("LOWER(TRIM(`{$statusField}`)) = ?", ['enrolled']);
                    $baseFemale = $baseFemale->whereRaw("LOWER(TRIM(`{$statusField}`)) = ?", ['enrolled']);
                }

                $maleCount   = $baseMale->count();
                $femaleCount = $baseFemale->count();
            }
        } catch (\Throwable $e) {
            logger()->warning('Student gender count error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Library Attendance by Grade (Current Month)
        |--------------------------------------------------------------------------
        */
        $attendanceByLevel       = [7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0];
        $attendanceMaleByLevel   = $attendanceByLevel;
        $attendanceFemaleByLevel = $attendanceByLevel;

        try {
            $genderField = null;
            try {
                $cols = collect(DB::select("SHOW COLUMNS FROM students"))->pluck('Field')->toArray();
                foreach (['sex', 'gender', 'gender_identity', 'sex_at_birth'] as $c) {
                    if (in_array($c, $cols)) { $genderField = $c; break; }
                }
            } catch (\Throwable $e) {
                $genderField = null;
            }

            if ($genderField) {
                $attendanceRows = DB::table('library_attendances as la')
                    ->join('section_students as s', 'la.student_id', '=', 's.studentid')
                    ->leftJoin('students as st', 's.studentid', '=', 'st.id')
                    ->select('s.levelid', DB::raw("LOWER(TRIM(COALESCE(st.{$genderField}, ''))) as gender"), DB::raw('COUNT(la.student_id) AS attendance_count'))
                    ->where('s.syid', 12)
                    ->whereBetween('s.levelid', [7, 12])
                    ->whereMonth('la.created_at', now()->month)
                    ->whereYear('la.created_at', now()->year)
                    ->groupBy('s.levelid', 'gender')
                    ->get();

                foreach ($attendanceRows as $row) {
                    $lvl = (int) $row->levelid;
                    $cnt = (int) $row->attendance_count;
                    $g   = trim(strtolower((string) $row->gender));
                    if (in_array($g, ['male', 'm'])) {
                        $attendanceMaleByLevel[$lvl] = $cnt;
                    } elseif (in_array($g, ['female', 'f'])) {
                        $attendanceFemaleByLevel[$lvl] = $cnt;
                    } else {
                        $attendanceByLevel[$lvl] += $cnt;
                    }
                }
                foreach ($attendanceByLevel as $k => $_) {
                    $attendanceByLevel[$k] = ($attendanceMaleByLevel[$k] ?? 0) + ($attendanceFemaleByLevel[$k] ?? 0) + ($attendanceByLevel[$k] ?? 0);
                }
            } else {
                $attendanceRows = DB::table('library_attendances as la')
                    ->join('section_students as s', 'la.student_id', '=', 's.studentid')
                    ->select('s.levelid', DB::raw('COUNT(la.student_id) AS attendance_count'))
                    ->where('s.syid', 12)
                    ->whereBetween('s.levelid', [7, 12])
                    ->whereMonth('la.created_at', now()->month)
                    ->whereYear('la.created_at', now()->year)
                    ->groupBy('s.levelid')
                    ->orderBy('s.levelid')
                    ->get();

                foreach ($attendanceRows as $row) {
                    $attendanceByLevel[(int) $row->levelid] = (int) $row->attendance_count;
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('Library attendance error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | GAD / Sex-Disaggregated Analytics (RA 9710)
        |--------------------------------------------------------------------------
        */
        $ipcrBySex    = ['male' => 0, 'female' => 0, 'unspecified' => 0];
        $tnaBySex     = ['male' => 0, 'female' => 0, 'unspecified' => 0];
        $idpBySex     = ['male' => 0, 'female' => 0, 'unspecified' => 0];
        $rewardsBySex = ['male' => 0, 'female' => 0, 'unspecified' => 0];
        $employeesByDivisionWithSex = [];

        $normSex = fn (?string $s): string => match (true) {
            in_array(strtolower(trim((string) $s)), ['male',   'm']) => 'male',
            in_array(strtolower(trim((string) $s)), ['female', 'f']) => 'female',
            default => 'unspecified',
        };

        try {
            foreach (DB::table('employee_ipcrs')
                ->join('users', 'employee_ipcrs.user_id', '=', 'users.id')
                ->select(DB::raw("LOWER(TRIM(COALESCE(users.sex,''))) as sex"), DB::raw('COUNT(*) as total'))
                ->groupBy('sex')->get() as $r) {
                $ipcrBySex[$normSex($r->sex)] += $r->total;
            }
        } catch (\Throwable $e) { logger()->warning('IPCR by sex: ' . $e->getMessage()); }

        try {
            foreach (DB::table('training_needs')
                ->join('users', 'training_needs.employee_id', '=', 'users.id')
                ->select(DB::raw("LOWER(TRIM(COALESCE(users.sex,''))) as sex"), DB::raw('COUNT(*) as total'))
                ->groupBy('sex')->get() as $r) {
                $tnaBySex[$normSex($r->sex)] += $r->total;
            }
        } catch (\Throwable $e) { logger()->warning('TNA by sex: ' . $e->getMessage()); }

        try {
            foreach (DB::table('individual_development_plans')
                ->join('users', 'individual_development_plans.employee_id', '=', 'users.id')
                ->select(DB::raw("LOWER(TRIM(COALESCE(users.sex,''))) as sex"), DB::raw('COUNT(*) as total'))
                ->groupBy('sex')->get() as $r) {
                $idpBySex[$normSex($r->sex)] += $r->total;
            }
        } catch (\Throwable $e) { logger()->warning('IDP by sex: ' . $e->getMessage()); }

        try {
            foreach (DB::table('reward_nominations')
                ->join('users', 'reward_nominations.nominee_id', '=', 'users.id')
                ->select(DB::raw("LOWER(TRIM(COALESCE(users.sex,''))) as sex"), DB::raw('COUNT(*) as total'))
                ->groupBy('sex')->get() as $r) {
                $rewardsBySex[$normSex($r->sex)] += $r->total;
            }
        } catch (\Throwable $e) { logger()->warning('Rewards by sex: ' . $e->getMessage()); }

        try {
            $divRows = DB::table('users')
                ->join('divisions', 'users.division_id', '=', 'divisions.id')
                ->select(
                    'divisions.id as div_id',
                    DB::raw("COALESCE(NULLIF(TRIM(divisions.acronym),''), divisions.division_name) as div_name"),
                    DB::raw("LOWER(TRIM(COALESCE(users.sex,''))) as sex"),
                    DB::raw('COUNT(DISTINCT LOWER(TRIM(users.name))) as cnt')
                )
                ->where('users.status', '!=', 'inactive')
                ->whereNotNull('users.emp_category')
                ->where('users.emp_category', '!=', '')
                ->groupBy('divisions.id', 'div_name', 'sex')
                ->get()
                ->groupBy('div_id');

            $employeesByDivisionWithSex = $divRows->map(function ($sexRows) use ($normSex) {
                $m = 0; $f = 0; $u = 0;
                foreach ($sexRows as $r) {
                    match ($normSex($r->sex)) {
                        'male'   => $m += $r->cnt,
                        'female' => $f += $r->cnt,
                        default  => $u += $r->cnt,
                    };
                }
                return ['name' => $sexRows->first()->div_name, 'male' => $m, 'female' => $f, 'unspecified' => $u, 'total' => $m + $f + $u];
            })->values()->sortByDesc('total')->take(10)->values()->toArray();
        } catch (\Throwable $e) { logger()->warning('Division sex: ' . $e->getMessage()); }

        /*
        |--------------------------------------------------------------------------
        | Render Dashboard
        |--------------------------------------------------------------------------
        */
        return Inertia::render('Dashboard', [
            // Employee stats
            'totalEmployees'      => $totalEmployees,
            'facultyCount'        => $facultyCount,
            'staffCount'          => $staffCount,
            'employeeMaleCount'   => $employeeMaleCount,
            'employeeFemaleCount' => $employeeFemaleCount,
            'facultyMaleCount'    => $facultyMaleCount,
            'facultyFemaleCount'  => $facultyFemaleCount,
            'staffMaleCount'      => $staffMaleCount,
            'staffFemaleCount'    => $staffFemaleCount,
            'activeDivisions'     => $activeDivisions,
            'employeesByDivision' => $employeesByDivision,

            // Scholar stats
            'scholarsCount' => $scholarsCount,
            'maleCount'     => $maleCount,
            'femaleCount'   => $femaleCount,

            // Library attendance
            'libraryAttendanceByGrade'       => array_values($attendanceByLevel),
            'libraryAttendanceMaleByGrade'   => array_values($attendanceMaleByLevel),
            'libraryAttendanceFemaleByGrade' => array_values($attendanceFemaleByLevel),

            // IPCR
            'ipcrByStatus'  => $ipcrByStatus,
            'ipcrForReview' => $ipcrForReview,
            'recentIPCRs'   => $recentIPCRs,

            // IT Job Requests
            'itjrByCategory' => $itjrByCategory,

            // Request overview
            'requestOverview'      => $requestOverview,
            'totalPendingRequests' => $totalPendingRequests,

            // Monthly trends
            'monthlyTrends' => $monthlyTrends,

            // L&D
            'lndStats' => $lndStats,

            // Rewards
            'rewardsStats' => $rewardsStats,

            // GAD / sex-disaggregated
            'ipcrBySex'                  => $ipcrBySex,
            'tnaBySex'                   => $tnaBySex,
            'idpBySex'                   => $idpBySex,
            'rewardsBySex'               => $rewardsBySex,
            'employeesByDivisionWithSex' => $employeesByDivisionWithSex,
        ]);
    }
}
