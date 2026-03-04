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
        $employeesByDivision = [];

        try {
            $totalEmployees = User::count();

            $facultyRole = Role::where('name', 'Faculty')->first();
            $staffRole   = Role::where('name', 'Staff')->first();
            if ($facultyRole) {
                $facultyCount = User::whereRaw('FIND_IN_SET(?, role_id)', [$facultyRole->id])->count();
            }
            if ($staffRole) {
                $staffCount = User::whereRaw('FIND_IN_SET(?, role_id)', [$staffRole->id])->count();
            }

            $activeDivisions     = Division::where('status', 'active')->count();
            $employeeMaleCount   = User::whereRaw("LOWER(TRIM(COALESCE(sex,''))) IN ('male','m')")->count();
            $employeeFemaleCount = User::whereRaw("LOWER(TRIM(COALESCE(sex,''))) IN ('female','f')")->count();

            $divs = Division::withCount('employees')->orderByDesc('employees_count')->take(10)->get();
            $employeesByDivision = $divs->map(fn ($d) => [
                'division' => $d->acronym ?: $d->division_name,
                'count'    => $d->employees_count,
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
                [
                    'label'     => 'Consultations',
                    'pending'   => Consultation::whereIn('status', ['Pending', 'Active'])->count(),
                    'completed' => Consultation::where('status', 'Completed')->count(),
                    'total'     => Consultation::count(),
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
                ['label' => 'Consultations',     'model' => Consultation::class],
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
        ]);
    }
}
