<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

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
                'status',
                'student_status',
                'enrollment_status',
                'enrolled',
                'enrollment',
                'status_desc'
            ];

            $statusField = collect($statusCandidates)
                ->first(fn ($f) => in_array($f, $columns));

            if ($statusField) {
                $scholarsCount = DB::table('students')
                    ->whereRaw("LOWER(TRIM(`{$statusField}`)) = ?", ['enrolled'])
                    ->count();
            } else {
                $scholarsCount = DB::table('students')->count();
            }
        } catch (\Throwable $e) {
            logger()->warning('Scholars count error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Faculty & Staff Count
        |--------------------------------------------------------------------------
        */
        $facultyCount = 0;
        $staffCount   = 0;
        try {
            $facultyCount = DB::table('users')
                ->where('role_id', 'like', '%3%')
                ->count();

            $staffCount = DB::table('users')
                ->where('role_id', 'like', '%4%')
                ->count();
        } catch (\Throwable $e) {
            logger()->warning('Faculty/Staff count error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Student Gender Count
        |--------------------------------------------------------------------------
        */
        $maleCount   = 0;
        $femaleCount = 0;

        try {
            $tables = ['students', 'student'];
            $genderField = null;
            $tableUsed   = null;
            $columns     = [];

            foreach ($tables as $table) {
                try {
                    $columns = collect(DB::select("SHOW COLUMNS FROM {$table}"))
                        ->pluck('Field')
                        ->toArray();

                    $genderCandidates = ['sex', 'gender', 'gender_identity', 'sex_at_birth'];
                    foreach ($genderCandidates as $g) {
                        if (in_array($g, $columns)) {
                            $genderField = $g;
                            $tableUsed   = $table;
                            break 2;
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            if ($genderField && $tableUsed) {
                // Determine status field in the same table so we can filter to enrolled students
                $statusCandidates = [
                    'status',
                    'student_status',
                    'enrollment_status',
                    'enrolled',
                    'enrollment',
                    'status_desc'
                ];

                $statusField = collect($statusCandidates)
                    ->first(fn ($f) => in_array($f, $columns));

                $baseMale = DB::table($tableUsed)
                    ->whereRaw("LOWER(TRIM(`{$genderField}`)) IN ('male','m')");

                $baseFemale = DB::table($tableUsed)
                    ->whereRaw("LOWER(TRIM(`{$genderField}`)) IN ('female','f')");

                if ($statusField) {
                    $baseMale = $baseMale->whereRaw("LOWER(TRIM(`{$statusField}`)) = ?", ['enrolled']);
                    $baseFemale = $baseFemale->whereRaw("LOWER(TRIM(`{$statusField}`)) = ?", ['enrolled']);
                }

                $maleCount   = $baseMale->count();
                $femaleCount = $baseFemale->count();
            }
        } catch (\Throwable $e) {
            logger()->warning('Gender count error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Library Attendance by Grade (Current Month)
        |--------------------------------------------------------------------------
        */
        $gradeLabels = [
            'Grade 7',
            'Grade 8',
            'Grade 9',
            'Grade 10',
            'Grade 11',
            'Grade 12',
        ];

        $attendanceByLevel = [
            7 => 0,
            8 => 0,
            9 => 0,
            10 => 0,
            11 => 0,
            12 => 0,
        ];

        // prepare male/female per-grade arrays
        $attendanceMaleByLevel = $attendanceByLevel;
        $attendanceFemaleByLevel = $attendanceByLevel;

        try {
            // detect gender field in students table
            $genderField = null;
            try {
                $cols = collect(DB::select("SHOW COLUMNS FROM students"))->pluck('Field')->toArray();
                $candidates = ['sex', 'gender', 'gender_identity', 'sex_at_birth'];
                foreach ($candidates as $c) {
                    if (in_array($c, $cols)) { $genderField = $c; break; }
                }
            } catch (\Throwable $e) {
                $genderField = null;
            }

            if ($genderField) {
                // group by level and gender
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
                    $lvl = (int)$row->levelid;
                    $cnt = (int)$row->attendance_count;
                    $g = trim(strtolower((string)$row->gender));
                    if (in_array($g, ['male','m'])) {
                        $attendanceMaleByLevel[$lvl] = $cnt;
                    } elseif (in_array($g, ['female','f'])) {
                        $attendanceFemaleByLevel[$lvl] = $cnt;
                    } else {
                        // unknown gender counts go into total (fallback)
                        $attendanceByLevel[$lvl] += $cnt;
                    }
                }
                // ensure combined total is populated for backward compatibility
                foreach ($attendanceByLevel as $k => $_) {
                    $attendanceByLevel[$k] = ($attendanceMaleByLevel[$k] ?? 0) + ($attendanceFemaleByLevel[$k] ?? 0) + ($attendanceByLevel[$k] ?? 0);
                }
            } else {
                // fallback to previous single-series count when gender field not available
                $attendanceRows = DB::table('library_attendances as la')
                    ->join('section_students as s', 'la.student_id', '=', 's.studentid')
                    ->select(
                        's.levelid',
                        DB::raw('COUNT(la.student_id) AS attendance_count')
                    )
                    ->where('s.syid', 12)
                    ->whereBetween('s.levelid', [7, 12])
                    ->whereMonth('la.created_at', now()->month)
                    ->whereYear('la.created_at', now()->year)
                    ->groupBy('s.levelid')
                    ->orderBy('s.levelid')
                    ->get();

                foreach ($attendanceRows as $row) {
                    $attendanceByLevel[(int)$row->levelid] = (int)$row->attendance_count;
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
            'scholarsCount' => $scholarsCount,
            'facultyCount'  => $facultyCount,
            'staffCount'    => $staffCount,
            'maleCount'     => $maleCount,
            'femaleCount'   => $femaleCount,

            // Bar graph data (counts per grade for current month)
            'libraryAttendanceByGrade' => array_values($attendanceByLevel),
            'libraryAttendanceMaleByGrade' => array_values($attendanceMaleByLevel),
            'libraryAttendanceFemaleByGrade' => array_values($attendanceFemaleByLevel),
        ]);
    }
}
