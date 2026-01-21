<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Compute scholars count: prefer counting rows where a status-like column equals 'Enrolled'
        $scholarsCount = 0;
        $statusField = null; // will hold the detected status column name if any
        try {
            $cols = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => $c->Field)->all();
            $statusCandidates = ['status','student_status','enrollment_status','enrolled','enrollment','status_desc'];
            foreach ($statusCandidates as $cand) {
                if (in_array($cand, $cols)) { $statusField = $cand; break; }
            }

            if ($statusField) {
                $scholarsCount = DB::table('students')->whereRaw("LOWER(TRIM(`{$statusField}`)) = ?", ['enrolled'])->count();
            } else {
                $scholarsCount = DB::table('students')->count();
            }
        } catch (\Throwable $e) {
            logger()->warning('Failed to compute scholars count for dashboard: '.$e->getMessage());
            $scholarsCount = 0;
        }

        // Faculty and Staff counts based on role_id: faculty -> 3, staff -> 4
        try {
            $facultyCount = DB::table('users')->where('role_id', 'like', '%3%')->count();
            $staffCount = DB::table('users')->where('role_id', 'like', '%4%')->count();
        } catch (\Throwable $e) {
            logger()->warning('Failed to compute faculty/staff counts: '.$e->getMessage());
            $facultyCount = 0;
            $staffCount = 0;
        }

        // Student gender breakdown (Male / Female)
        $maleCount = 0;
        $femaleCount = 0;
        try {
            $tablesToTry = ['students', 'student'];
            $genderField = null;
            $foundTable = null;
            $cols = null;
            foreach ($tablesToTry as $tbl) {
                try {
                    $cols = collect(DB::select("SHOW COLUMNS FROM {$tbl}"))->map(fn($c) => $c->Field)->all();
                } catch (\Throwable $e) {
                    continue;
                }
                $genderCandidates = ['sex', 'gender', 'gender_identity', 'sex_at_birth'];
                foreach ($genderCandidates as $cand) {
                    if (in_array($cand, $cols)) { $genderField = $cand; $foundTable = $tbl; break 2; }
                }
            }

            if ($genderField && $foundTable) {
                // Determine if this table has a status-like field and only count Enrolled students
                $statusCandidates = ['status','student_status','enrollment_status','enrolled','enrollment','status_desc'];
                $statusFieldForTable = null;
                foreach ($statusCandidates as $cand) {
                    if (in_array($cand, $cols)) { $statusFieldForTable = $cand; break; }
                }

                $baseQuery = DB::table($foundTable)->whereRaw("LOWER(TRIM(`{$genderField}`)) IN (?, ?)", ['male', 'm']);
                $baseQueryFemale = DB::table($foundTable)->whereRaw("LOWER(TRIM(`{$genderField}`)) IN (?, ?)", ['female', 'f']);

                if ($statusFieldForTable) {
                    $baseQuery->whereRaw("LOWER(TRIM(`{$statusFieldForTable}`)) = ?", ['enrolled']);
                    $baseQueryFemale->whereRaw("LOWER(TRIM(`{$statusFieldForTable}`)) = ?", ['enrolled']);
                } elseif ($statusField) {
                    // fallback to the previously detected students status field (if present)
                    $baseQuery->whereRaw("LOWER(TRIM(`{$statusField}`)) = ?", ['enrolled']);
                    $baseQueryFemale->whereRaw("LOWER(TRIM(`{$statusField}`)) = ?", ['enrolled']);
                }

                $maleCount = $baseQuery->count();
                $femaleCount = $baseQueryFemale->count();
            }
        } catch (\Throwable $e) {
            logger()->warning('Failed to compute student gender counts: '.$e->getMessage());
            $maleCount = 0; $femaleCount = 0;
        }

        return Inertia::render('Dashboard', [
            'scholarsCount' => $scholarsCount,
            'facultyCount' => $facultyCount,
            'staffCount' => $staffCount,
            'maleCount' => $maleCount,
            'femaleCount' => $femaleCount,
        ]);
    }
}
