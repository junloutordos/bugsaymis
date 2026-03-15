<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\LibraryAttendance;

class LibraryAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 20;
        $query = LibraryAttendance::query()->orderBy('scanned_at', 'desc');

        if ($q = $request->input('q')) {
            $query->where(function($qr) use ($q) {
                $qr->where('pisay_systemid', 'like', "%$q%")
                   ->orWhere('student_name', 'like', "%$q%");
            });
        }

        $attendances = $query->paginate($perPage)->appends($request->only('q'));

        return Inertia::render('Library/Attendance', [
            'attendances' => $attendances,
            'q' => $request->input('q'),
        ]);
    }

    /**
     * Printable statistics report for a date range
     */
    public function report(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $start = date('Y-m-d', strtotime($request->input('start')));
        $end = date('Y-m-d', strtotime($request->input('end')));

        $rows = DB::table('library_attendances')
            ->whereDate('scanned_at', '>=', $start)
            ->whereDate('scanned_at', '<=', $end)
            ->orderBy('scanned_at')
            ->get();

        // totals and breakdown by week (grouped by week start date)
        $total = $rows->count();
        $byWeek = $rows->groupBy(function ($r) {
            try {
                return Carbon::parse($r->scanned_at)->startOfWeek()->format('Y-m-d');
            } catch (\Throwable $e) {
                return date('Y-m-d', strtotime($r->scanned_at));
            }
        })->map->count();

        // Prepare grade-level attendance (7-12) separated by sex similar to Dashboard
        $gradeLevels = [7,8,9,10,11,12];
        $attendanceByLevel = array_fill_keys($gradeLevels, 0);
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
                // Count repeated scans (rows) per grade and gender within the date range.
                // Use each student's latest section (by syid) as their grade to avoid duplicates from multiple section records.
                $attendanceRows = DB::table('library_attendances as la')
                    ->join('students as st', 'la.student_id', '=', 'st.id')
                    ->select(
                        DB::raw("(SELECT s.levelid FROM section_students s WHERE s.studentid = st.id ORDER BY s.syid DESC LIMIT 1) as levelid"),
                        DB::raw("LOWER(TRIM(COALESCE(st.{$genderField}, ''))) as gender"),
                        DB::raw('COUNT(*) AS attendance_count')
                    )
                    ->whereDate('la.scanned_at', '>=', $start)
                    ->whereDate('la.scanned_at', '<=', $end)
                    ->groupBy('levelid', 'gender')
                    ->get();

                // reset to ensure keys exist for 7..12
                foreach ($gradeLevels as $lvlKey) {
                    $attendanceMaleByLevel[$lvlKey] = 0;
                    $attendanceFemaleByLevel[$lvlKey] = 0;
                    $attendanceByLevel[$lvlKey] = 0;
                }

                foreach ($attendanceRows as $row) {
                    $lvl = $row->levelid ? (int)$row->levelid : null;
                    $cnt = (int)$row->attendance_count;
                    $g = trim(strtolower((string)$row->gender));
                    if (!$lvl || !in_array($lvl, $gradeLevels)) continue;
                    if (in_array($g, ['male','m'])) {
                        $attendanceMaleByLevel[$lvl] = ($attendanceMaleByLevel[$lvl] ?? 0) + $cnt;
                    } elseif (in_array($g, ['female','f'])) {
                        $attendanceFemaleByLevel[$lvl] = ($attendanceFemaleByLevel[$lvl] ?? 0) + $cnt;
                    } else {
                        $attendanceByLevel[$lvl] = ($attendanceByLevel[$lvl] ?? 0) + $cnt;
                    }
                }

                // ensure combined totals
                foreach ($gradeLevels as $k) {
                    $attendanceByLevel[$k] = ($attendanceMaleByLevel[$k] ?? 0) + ($attendanceFemaleByLevel[$k] ?? 0) + ($attendanceByLevel[$k] ?? 0);
                }
            } else {
                // fallback to single-series when no gender field
                $attendanceRows = DB::table('library_attendances as la')
                    ->join('section_students as s', 'la.student_id', '=', 's.studentid')
                    ->select('s.levelid', DB::raw('COUNT(la.student_id) AS attendance_count'))
                    ->whereBetween('s.levelid', [7, 12])
                    ->whereDate('la.scanned_at', '>=', $start)
                    ->whereDate('la.scanned_at', '<=', $end)
                    ->groupBy('s.levelid')
                    ->orderBy('s.levelid')
                    ->get();

                foreach ($attendanceRows as $row) {
                    if (in_array((int)$row->levelid, $gradeLevels)) {
                        $attendanceByLevel[(int)$row->levelid] = (int)$row->attendance_count;
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('Library attendance report error: ' . $e->getMessage());
        }

            // Also compute total male/female scans for the given date range
            $maleTotal = 0;
            $femaleTotal = 0;
            try {
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
                    $genderRows = DB::table('library_attendances as la')
                        ->join('students as st', 'la.student_id', '=', 'st.id')
                        ->select(DB::raw("LOWER(TRIM(COALESCE(st.{$genderField}, ''))) as gender"), DB::raw('COUNT(*) AS cnt'))
                        ->whereDate('la.scanned_at', '>=', $start)
                        ->whereDate('la.scanned_at', '<=', $end)
                        ->groupBy('gender')
                        ->get();

                    foreach ($genderRows as $gr) {
                        $g = trim(strtolower((string)$gr->gender));
                        $cnt = (int)$gr->cnt;
                        if (in_array($g, ['male','m'])) {
                            $maleTotal += $cnt;
                        } elseif (in_array($g, ['female','f'])) {
                            $femaleTotal += $cnt;
                        }
                    }
                }
            } catch (\Throwable $e) {
                logger()->warning('Library sex totals error: ' . $e->getMessage());
            }

        return view('library.statistics_report', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
            'total' => $total,
            'byWeek' => $byWeek,
            'libraryAttendanceByGrade' => array_values($attendanceByLevel),
            'libraryAttendanceMaleByGrade' => array_values($attendanceMaleByLevel),
            'libraryAttendanceFemaleByGrade' => array_values($attendanceFemaleByLevel),
                'maleTotal' => $maleTotal,
                'femaleTotal' => $femaleTotal,
        ]);
    }
}
