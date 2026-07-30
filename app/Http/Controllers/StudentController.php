<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentUpdateRequest;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\StudentProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Picqer\Barcode\BarcodeGeneratorSVG;

class StudentController extends Controller
{
    public function __construct(
        private DigitalSignatureService $sigService,
        private StudentProfileService $studentProfileService,
    ) {}

    /** Explicit, known-good column list for the students search box (avoids guessing column names). */
    private const SEARCHABLE_COLUMNS = [
        'lastname', 'firstname', 'middlename', 'nickname',
        'student_email', 'lrn', 'pisaysystemID',
        'sex', 'contactperson', 'contactno1',
    ];

    public function index(Request $request)
    {
        $perPage = 30;
        $search = $request->input('q');
        $tab = $request->input('tab') === 'inactive' ? 'inactive' : 'active';
        $sectionId = $request->input('section_id');
        $gradeLevel = $request->input('grade_level');
        $sex = $request->input('sex');

        $currentSY = SchoolYear::where('is_current', true)->first();

        $allCols = collect(DB::select('SHOW COLUMNS FROM students'))->map(fn ($c) => $c->Field)->all();

        // Current-SY enrollment (if any) for each student, joined once and reused for
        // both the tab split and the section/grade columns/filters.
        $currentEnrollmentJoin = function ($join) use ($currentSY) {
            $join->on('se.student_id', '=', 'students.id')
                ->where('se.school_year_id', '=', $currentSY?->id ?? 0);
        };

        $baseQuery = DB::table('students')
            ->leftJoin('student_enrollments as se', $currentEnrollmentJoin)
            ->leftJoin('sections as sec', 'sec.id', '=', 'se.section_id');

        if ($tab === 'active') {
            $baseQuery->where('se.status', 'enrolled');
        } else {
            $baseQuery->where(function ($q) {
                $q->whereNull('se.id')->orWhere('se.status', '<>', 'enrolled');
            });
        }

        if ($search) {
            $term = "%{$search}%";
            $searchable = array_values(array_intersect(self::SEARCHABLE_COLUMNS, $allCols));
            if (!empty($searchable)) {
                $baseQuery->where(function ($q) use ($searchable, $term) {
                    foreach ($searchable as $i => $field) {
                        $column = "students.{$field}";
                        if ($i === 0) $q->where($column, 'like', $term);
                        else $q->orWhere($column, 'like', $term);
                    }
                });
            }
        }

        // Section/grade filters only apply meaningfully to the active tab (inactive
        // students have no current-SY section), but are accepted either way.
        if ($sectionId) {
            $baseQuery->where('se.section_id', $sectionId);
        }
        if ($gradeLevel) {
            $baseQuery->where('se.grade_level', $gradeLevel);
        }
        if ($sex) {
            $baseQuery->where('students.sex', $sex);
        }

        $students = $baseQuery
            ->select([
                'students.*',
                'se.grade_level as current_grade_level',
                'se.status as current_enrollment_status',
                'sec.sectionname as current_section_name',
                'sec.id as current_section_id',
            ])
            ->orderBy('students.lastname')
            ->orderBy('students.firstname')
            ->paginate($perPage)
            ->appends($request->only('q', 'tab', 'section_id', 'grade_level', 'sex'));

        // Tab counts (independent of the current tab's own filters, but respect search/section/grade/sex)
        $countsQuery = fn ($activeTab) => DB::table('students')
            ->leftJoin('student_enrollments as se', $currentEnrollmentJoin)
            ->when($activeTab === 'active', fn ($q) => $q->where('se.status', 'enrolled'))
            ->when($activeTab === 'inactive', fn ($q) => $q->where(function ($qq) {
                $qq->whereNull('se.id')->orWhere('se.status', '<>', 'enrolled');
            }))
            ->when($search, function ($q) use ($search, $allCols) {
                $term = "%{$search}%";
                $searchable = array_values(array_intersect(self::SEARCHABLE_COLUMNS, $allCols));
                if (!empty($searchable)) {
                    $q->where(function ($qq) use ($searchable, $term) {
                        foreach ($searchable as $i => $field) {
                            $column = "students.{$field}";
                            if ($i === 0) $qq->where($column, 'like', $term);
                            else $qq->orWhere($column, 'like', $term);
                        }
                    });
                }
            })
            ->count();

        // Dropdown options: sections/grades scoped to the current school year
        $sectionOptions = $currentSY
            ? DB::table('sections')
                ->where('syid', $currentSY->id)
                ->orderBy('levelid')
                ->orderBy('sectionname')
                ->get(['id', 'sectionname', 'levelid'])
            : collect();

        $gradeOptions = $sectionOptions->pluck('levelid')->unique()->sort()->values();

        $columns = collect(DB::select('SHOW COLUMNS FROM students'))->map(fn ($c) => (array) $c)->all();

        return Inertia::render('Students/Index', [
            'students' => $students,
            'columns' => $columns,
            'writable_columns' => $this->studentProfileService->writableColumns($allCols),
            'q' => $search,
            'tab' => $tab,
            'filters' => [
                'section_id' => $sectionId,
                'grade_level' => $gradeLevel,
                'sex' => $sex,
            ],
            'tab_counts' => [
                'active' => $countsQuery('active'),
                'inactive' => $countsQuery('inactive'),
            ],
            'section_options' => $sectionOptions,
            'grade_options' => $gradeOptions,
            'current_school_year' => $currentSY?->name,
            'can_manage_students' => auth()->user()->hasPermission('manage-students') || auth()->user()->isSuperAdmin(),
        ]);
    }

    public function create()
    {
        $columns = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => (array) $c)->all();
        return Inertia::render('Students/Index', [
            'students' => [],
            'columns' => $columns,
            'writable_columns' => $this->studentProfileService->writableColumns(
                collect($columns)->pluck('Field')->all()
            ),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-students');

        $allowedColumns = $this->studentProfileService->writableColumns(
            collect(DB::select('SHOW COLUMNS FROM students'))->pluck('Field')->all()
        );

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
            'writable_columns' => $this->studentProfileService->writableColumns(
                collect($columns)->pluck('Field')->all()
            ),
            'editing' => $id,
        ]);
    }

    public function update(StudentUpdateRequest $request, $id)
    {
        $student = DB::table('students')->where('id', $id)->first();
        abort_if(! $student, 404);

        $columns = collect(DB::select('SHOW COLUMNS FROM students'));
        $data = $this->studentProfileService->normalizeForStorage($request->validated(), $columns);
        if ($data === []) {
            throw ValidationException::withMessages([
                'student' => 'No editable student information was submitted.',
            ]);
        }

        $changes = $this->studentProfileService->changedValues($student, $data);

        if ($changes === []) {
            return back()->with('success', 'No changes detected.');
        }

        if (Schema::hasColumn('students', 'date_updated')) {
            $changes['date_updated'] = now()->format('Y-m-d');
        }

        DB::table('students')->where('id', $id)->update($changes);

        return back()->with('success', 'Student updated.');
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
            filled($student->barangay ?? '') ? 'Brgy. ' . $student->barangay : null,
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
