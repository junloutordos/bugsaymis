<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LibraryAttendance;
use Carbon\Carbon;

class LibraryKioskController extends Controller
{
    public function index()
    {
        return view('library.kiosk');
    }

    public function scan(Request $request)
    {
        $pisay = $request->input('pisay');
        if (!$pisay) {
            return response()->json(['error' => 'Missing pisay_systemid'], 422);
        }

        // Determine students table pisay column name variations
        $cols = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => $c->Field)->all();
        $pisayCandidates = ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id','pisayid'];
        $pisayCol = null;
        foreach ($pisayCandidates as $c) {
            if (in_array($c, $cols)) { $pisayCol = $c; break; }
        }

        $student = null;
        if ($pisayCol) {
            $student = DB::table('students')->where($pisayCol, $pisay)->first();
        } else {
            $student = DB::table('students')->whereRaw('LOWER(CONCAT_WS(" ", '.implode(', ', array_map(fn($c)=>"`$c`", $cols)).')) LIKE ?', ["%$pisay%"])->first();
        }

        $studentId = $student?->id ?? null;
        $studentName = null;
        if ($student) {
            // try to get first and last name
            $names = [];
            foreach (['first_name','firstname','fname','given_name'] as $f) if (isset($student->$f)) { $names[] = $student->$f; break; }
            foreach (['last_name','lastname','lname','surname'] as $f) if (isset($student->$f)) { array_unshift($names, $student->$f); break; }
            if (empty($names)) {
                // fallback: take first non-empty field
                foreach ((array) $student as $val) { if ($val) { $studentName = $val; break; } }
            } else {
                $studentName = trim(implode(' ', $names));
            }
        }

        $attendance = LibraryAttendance::create([
            'pisay_systemid' => $pisay,
            'student_id' => $studentId,
            'student_name' => $studentName,
            // store scanned time in Philippine Standard Time (Asia/Manila)
            'scanned_at' => Carbon::now('Asia/Manila'),
        ]);

        return response()->json([
            'ok' => true,
            'student_name' => $studentName,
            'attendance_id' => $attendance->id,
        ]);
    }
}
