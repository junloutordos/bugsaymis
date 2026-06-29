<?php

namespace App\Http\Controllers;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use App\Services\DigitalSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Picqer\Barcode\BarcodeGeneratorSVG;

class StudentController extends Controller
{
    public function __construct(
        private DigitalSignatureService $sigService,
    ) {}

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
            'can_manage_students' => auth()->user()->hasPermission('manage-students') || auth()->user()->isSuperAdmin(),
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
        'sex', 'gender', 'birthday', 'birthdate', 'birth_date', 'age',
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

        DB::table('students')->where('id', $id)->update($data);

        return redirect()->route('students.index')->with('success', 'Student updated.');
    }

    public function destroy($id)
    {
        DB::table('students')->where('id', $id)->delete();
        return redirect()->route('students.index')->with('success', 'Student deleted.');
    }

    /**
     * CR-80 student ID card preview / print page (front + back).
     */
    public function idCard($id)
    {
        $student = DB::table('students')->where('id', $id)->first();
        abort_if(! $student, 404);

        $currentSY = SchoolYear::where('is_current', true)->first();

        $enrollment = StudentEnrollment::where('student_id', $id)
            ->when($currentSY, fn ($q) => $q->where('school_year_id', $currentSY->id))
            ->with('section')
            ->first();

        $middle   = $student->middlename ? ' ' . mb_strtoupper(mb_substr($student->middlename, 0, 1)) . '.' : '';
        $fullName = mb_strtoupper(trim("{$student->lastname}, {$student->firstname}{$middle}"));

        $barcodeSvg = $student->pisaysystemID
            ? (new BarcodeGeneratorSVG())->getBarcode($student->pisaysystemID, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 40)
            : null;

        $ocdUser = User::whereHas('roles', fn ($q) => $q->where('name', 'OCD'))->first();

        $address = implode(', ', array_filter([
            $student->houseno,
            $student->municipal,
            $student->province,
        ], fn ($v) => filled($v)));

        return Inertia::render('Students/IdCard', [
            'student' => [
                'id'  => $student->id,
                'full_name' => $fullName,
                'lrn'       => $student->lrn,
                'barcode'   => $student->pisaysystemID ?: null,
                'img'       => $student->img,
            ],
            'enrollment' => $enrollment ? [
                'grade_level' => $enrollment->grade_level,
                'section'     => $enrollment->section?->sectionname,
            ] : null,
            'school_year' => $currentSY?->name,
            'barcode_svg' => $barcodeSvg,
            'ocd' => [
                'name'          => 'MELBA C. PATACSIL, PhD',
                'position'      => 'Campus Director',
                'signature_uri' => $ocdUser ? $this->sigService->getSignatureDataUri($ocdUser) : null,
            ],
            'emergency' => [
                'guardian_name' => $student->contactperson ?: null,
                'contact_no'    => $student->contactno1 ?: null,
                'address'       => $address ?: null,
            ],
        ]);
    }

    public function proxyPhoto(int $id)
    {
        $img = DB::table('students')->where('id', $id)->value('img');
        abort_if(! $img, 404);

        if (str_contains($img, '/')) {
            abort_if(! Storage::disk('s3')->exists($img), 404);
            $content = Storage::disk('s3')->get($img);
            $mime = Storage::disk('s3')->mimeType($img) ?: 'image/jpeg';
            return response($content, 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        $localPath = storage_path("app/public/students_profile_picture/{$img}");
        abort_if(! file_exists($localPath), 404);
        return response()->file($localPath, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function updatePhoto(Request $request, int $id)
    {
        $this->authorize('manage-students');

        $request->validate(['photo_base64' => 'required|string']);

        $dataUri = $request->input('photo_base64');
        if (! preg_match('/^data:(image\/[\w+\-]+);base64,(.+)$/s', $dataUri, $m)) {
            return response()->json(['error' => 'Invalid image data'], 422);
        }

        $mime   = $m[1];
        $ext    = str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'webp') ? 'webp' : 'jpg');
        $binary = base64_decode($m[2]);

        $existing = DB::table('students')->where('id', $id)->value('img');
        if ($existing && str_contains($existing, '/') && Storage::disk('s3')->exists($existing)) {
            Storage::disk('s3')->delete($existing);
        }

        $s3Key = "students/profile_pictures/{$id}_" . time() . ".{$ext}";
        Storage::disk('s3')->put($s3Key, $binary, ['ContentType' => $mime]);

        DB::table('students')->where('id', $id)->update(['img' => $s3Key]);

        return response()->json(['success' => true, 'img' => $s3Key]);
    }
}
