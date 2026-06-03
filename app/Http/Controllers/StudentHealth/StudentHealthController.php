<?php

namespace App\Http\Controllers\StudentHealth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StudentHealthController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('students.health.view');

        $search = $request->input('search', '');

        $query = DB::table('students')
            ->select('id', 'lastname', 'firstname', 'middlename', 'pisaysystemID', 'batch', 'status', 'sex');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('lastname', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('pisaysystemID', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('batch', 'desc')->orderBy('lastname')->orderBy('firstname')->get();

        // Flag students who have any health record
        $ids = $students->pluck('pisaysystemID')->filter()->values()->toArray();
        $withRecords = [];
        if ($ids) {
            foreach (['student_allergies', 'student_immunization_history', 'student_medical_history', 'student_vitamins_history'] as $t) {
                $pids = DB::table($t)->whereIn('pisaySystemID', $ids)->distinct()->pluck('pisaySystemID');
                foreach ($pids as $pid) {
                    $withRecords[$pid] = true;
                }
            }
        }

        $students = $students->map(function ($s) use ($withRecords) {
            $s->has_health_record = isset($withRecords[$s->pisaysystemID]);
            return $s;
        });

        return Inertia::render('StudentHealth/Index', [
            'students' => $students,
            'filters'  => ['search' => $search],
        ]);
    }

    public function show(string $pisaySystemID)
    {
        $this->authorize('students.health.view');

        $student = DB::table('students')
            ->where('pisaysystemID', $pisaySystemID)
            ->first();

        abort_if(! $student, 404, 'Student not found.');

        $health = [
            'allergies'      => DB::table('student_allergies')
                ->where('pisaySystemID', $pisaySystemID)
                ->orderBy('created_at')
                ->get()->toArray(),
            'immunizations'  => DB::table('student_immunization_history')
                ->where('pisaySystemID', $pisaySystemID)
                ->orderBy('date_administered')
                ->get()->toArray(),
            'medical_history'=> DB::table('student_medical_history')
                ->where('pisaySystemID', $pisaySystemID)
                ->orderBy('date_sustained')
                ->get()->toArray(),
            'vitamins'       => DB::table('student_vitamins_history')
                ->where('pisaySystemID', $pisaySystemID)
                ->orderBy('date_taken')
                ->get()->toArray(),
        ];

        return Inertia::render('StudentHealth/Show', [
            'student' => $student,
            'health'  => $health,
        ]);
    }
}
