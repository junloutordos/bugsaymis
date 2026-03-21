<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 10;
        $search = $request->input('q');

        $query = DB::table('students');

        // If there's a status-like column, default to showing only enrolled students
        $allCols = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => $c->Field)->all();
        $statusCandidates = ['status','student_status','enrollment_status','enrolled','enrollment','status_desc'];
        $statusField = null;
        foreach ($statusCandidates as $cand) {
            if (in_array($cand, $allCols)) { $statusField = $cand; break; }
        }
        if ($statusField) {
            $query->where($statusField, 'Enrolled');
        }
        if ($search) {
            $term = "%{$search}%";

            // detect available columns and build where clauses only for existing fields
            $cols = $allCols; // reuse

            $candidates = [
                'last_name','lastname','lname',
                'first_name','firstname','fname',
                'middle_name','middlename','mname',
                'birthday','birthdate','dob',
                'sex','gender'
            ];

            $searchable = array_values(array_intersect($candidates, $cols));

            if (empty($searchable)) {
                // fallback: search all varchar/text columns except id/timestamps
                $searchable = [];
                foreach (DB::select("SHOW COLUMNS FROM students") as $c) {
                    $c = (array) $c;
                    $type = strtolower($c['Type']);
                    if (str_starts_with($type, 'varchar') || str_contains($type, 'text') || str_starts_with($type, 'char')) {
                        $field = $c['Field'];
                        if (!in_array($field, ['id','created_at','updated_at'])) $searchable[] = $field;
                    }
                }
            }

            if (!empty($searchable)) {
                $query->where(function ($q) use ($searchable, $term) {
                    foreach ($searchable as $i => $field) {
                        if ($i === 0) $q->where($field, 'like', $term);
                        else $q->orWhere($field, 'like', $term);
                    }
                });
            }
        }

        // Determine ordering fields (handle different column naming conventions)
        $lastCandidates = ['last_name','lastname','lname','surname'];
        $firstCandidates = ['first_name','firstname','fname','given_name'];

        $lastField = null;
        foreach ($lastCandidates as $cand) {
            if (in_array($cand, $allCols)) { $lastField = $cand; break; }
        }
        $firstField = null;
        foreach ($firstCandidates as $cand) {
            if (in_array($cand, $allCols)) { $firstField = $cand; break; }
        }

        if ($lastField) {
            $query->orderBy($lastField);
            if ($firstField) $query->orderBy($firstField);
        } else {
            $query->orderBy('id');
        }

        $students = $query->paginate($perPage)->appends($request->only('q'));

        $columns = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => (array) $c)->all();

        return Inertia::render('Students/Index', [
            'students' => $students,
            'columns' => $columns,
            'q' => $search,
        ]);
    }

    public function create()
    {
        $columns = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => (array) $c)->all();
        return Inertia::render('Students/Index', [
            'students' => [],
            'columns' => $columns,
        ]);
    }

    // Columns that may be written via the form — anything not in this list is ignored.
    private const WRITABLE_COLUMNS = [
        'student_id', 'lrn', 'first_name', 'last_name', 'middle_name', 'suffix',
        'firstname', 'lastname', 'middlename', 'fname', 'lname', 'mname',
        'given_name', 'surname', 'name', 'full_name',
        'sex', 'gender', 'birthdate', 'birth_date', 'age',
        'grade_level', 'grade', 'year_level', 'strand', 'track', 'section', 'section_id',
        'address', 'barangay', 'city', 'municipality', 'province', 'zip',
        'contact_no', 'phone', 'mobile', 'email',
        'parent_name', 'guardian_name', 'parent_contact', 'guardian_contact',
        'school_year', 'sy', 'semester',
        'status', 'student_status', 'enrollment_status',
        'campus', 'campus_id',
    ];

    public function store(Request $request)
    {
        $this->authorize('manage-students');

        $allowedColumns = collect(DB::select("SHOW COLUMNS FROM students"))
            ->map(fn($c) => $c->Field)
            ->intersect(self::WRITABLE_COLUMNS)
            ->all();

        $data = [];
        foreach ($allowedColumns as $col) {
            if ($request->has($col)) {
                $data[$col] = $request->input($col);
            }
        }

        DB::table('students')->insert($data);

        return redirect()->route('students.index')->with('success', 'Student created.');
    }

    public function edit($id)
    {
        $student = DB::table('students')->where('id', $id)->first();
        if (!$student) return redirect()->route('students.index')->withErrors('Student not found');

        $columns = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => (array) $c)->all();

        return Inertia::render('Students/Index', [
            'students' => [$student],
            'columns' => $columns,
            'editing' => $id,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('manage-students');

        $allowedColumns = collect(DB::select("SHOW COLUMNS FROM students"))
            ->map(fn($c) => $c->Field)
            ->intersect(self::WRITABLE_COLUMNS)
            ->all();

        $data = [];
        foreach ($allowedColumns as $col) {
            if ($request->has($col)) {
                $data[$col] = $request->input($col);
            }
        }

        DB::table('students')->where('id', $id)->update($data + ['updated_at' => now()]);

        return redirect()->route('students.index')->with('success', 'Student updated.');
    }

    public function destroy($id)
    {
        DB::table('students')->where('id', $id)->delete();
        return redirect()->route('students.index')->with('success', 'Student deleted.');
    }
}
