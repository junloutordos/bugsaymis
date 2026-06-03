<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('students.enrollment.view');

        $currentId    = SchoolYear::where('is_current', true)->value('id');
        $schoolYearId = (int) $request->input('school_year_id', $currentId);

        $schoolYears = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        // Sections for this school year, grouped by grade level
        $sections = Section::where('school_year_id', $schoolYearId)
            ->where('is_active', true)
            ->with(['adviserUser:id,name'])
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get();

        // Enrollment counts per section for this school year
        $enrollmentCounts = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->selectRaw('section_id, status, COUNT(*) as count')
            ->groupBy('section_id', 'status')
            ->get()
            ->groupBy('section_id')
            ->map(fn ($rows) => $rows->keyBy('status')->map->count);

        $sectionData = $sections->map(fn ($s) => [
            'id'           => $s->id,
            'name'         => $s->sectionname,
            'grade_level'  => $s->levelid,
            'capacity'     => $s->capacity,
            'adviser_name' => $s->adviserUser?->name,
            'enrolled'     => $enrollmentCounts->get($s->id, collect())->get('enrolled', 0),
            'dropped'      => $enrollmentCounts->get($s->id, collect())->get('dropped', 0),
            'transferred'  => $enrollmentCounts->get($s->id, collect())->get('transferred_out', 0),
        ]);

        return Inertia::render('Registrar/Enrollment/Index', [
            'sections'           => $sectionData,
            'schoolYears'        => $schoolYears,
            'selectedSchoolYear' => $schoolYearId,
            'gradeLevels'        => range(7, 12),
        ]);
    }

    // ── Students for a section ────────────────────────────────────────────────

    public function sectionStudents(Request $request, int $sectionId): JsonResponse
    {
        $this->authorize('students.enrollment.view');

        $schoolYearId = (int) $request->input('school_year_id',
            SchoolYear::where('is_current', true)->value('id'));

        $enrollments = StudentEnrollment::where('section_id', $sectionId)
            ->where('school_year_id', $schoolYearId)
            ->with('encodedBy:id,name')
            ->orderBy('created_at')
            ->get();

        // Attach student info (MyISAM join — manual pull)
        $studentIds = $enrollments->pluck('student_id')->unique()->values();
        $students   = Student::whereIn('id', $studentIds)
            ->get(['id', 'firstname', 'lastname', 'middlename', 'pisaysystemID', 'lrn', 'sex'])
            ->keyBy('id');

        $result = $enrollments->map(fn ($e) => [
            'id'              => $e->id,
            'student_id'      => $e->student_id,
            'full_name'       => $students->get($e->student_id)?->full_name ?? 'Unknown',
            'pisays_id'       => $students->get($e->student_id)?->pisaysystemID,
            'lrn'             => $students->get($e->student_id)?->lrn,
            'sex'             => $students->get($e->student_id)?->sex,
            'enrollment_type' => $e->enrollment_type,
            'status'          => $e->status,
            'enrollment_date' => $e->enrollment_date?->format('Y-m-d'),
            'notes'           => $e->notes,
            'encoded_by_name' => $e->encodedBy?->name,
        ]);

        return response()->json($result);
    }

    // ── Search students for enrollment ────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $this->authorize('students.enrollment.view');

        $q            = trim($request->input('q', ''));
        $schoolYearId = (int) $request->input('school_year_id',
            SchoolYear::where('is_current', true)->value('id'));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        // Already-enrolled student IDs for this school year (exclude from results)
        $enrolledIds = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->whereIn('status', ['enrolled', 'on_leave'])
            ->pluck('student_id')
            ->toArray();

        $students = Student::where(function ($query) use ($q) {
                $query->where('lastname', 'like', "%{$q}%")
                      ->orWhere('firstname', 'like', "%{$q}%")
                      ->orWhere('pisaysystemID', 'like', "%{$q}%")
                      ->orWhere('lrn', 'like', "%{$q}%");
            })
            ->whereNotIn('id', $enrolledIds)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(20)
            ->get(['id', 'firstname', 'lastname', 'middlename', 'pisaysystemID', 'lrn', 'sex', 'batch']);

        return response()->json($students->map(fn ($s) => [
            'id'         => $s->id,
            'full_name'  => $s->full_name,
            'pisays_id'  => $s->pisaysystemID,
            'lrn'        => $s->lrn,
            'sex'        => $s->sex,
            'batch'      => $s->batch,
        ]));
    }

    // ── Enroll a student ──────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $data = $request->validate([
            'student_id'       => ['required', 'integer'],
            'school_year_id'   => ['required', 'integer', 'exists:school_years,id'],
            'section_id'       => ['required', 'integer'],
            'enrollment_type'  => ['required', Rule::in(['new', 'returning', 'transferee', 'returnee'])],
            'enrollment_date'  => ['required', 'date'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ]);

        // Verify student exists
        abort_unless(Student::where('id', $data['student_id'])->exists(), 404, 'Student not found.');

        // Verify section exists and belongs to correct school year
        $section = Section::where('id', $data['section_id'])
            ->where('school_year_id', $data['school_year_id'])
            ->firstOrFail();

        // Check capacity
        $currentCount = StudentEnrollment::where('section_id', $section->id)
            ->where('school_year_id', $data['school_year_id'])
            ->where('status', 'enrolled')
            ->count();

        abort_if($currentCount >= $section->capacity, 422, 'Section is already at capacity.');

        // Guard: already enrolled this school year
        abort_if(
            StudentEnrollment::where('student_id', $data['student_id'])
                ->where('school_year_id', $data['school_year_id'])
                ->whereIn('status', ['enrolled', 'on_leave'])
                ->exists(),
            422,
            'Student is already enrolled for this school year.'
        );

        StudentEnrollment::create([
            'student_id'      => $data['student_id'],
            'school_year_id'  => $data['school_year_id'],
            'section_id'      => $section->id,
            'grade_level'     => $section->levelid,
            'enrollment_type' => $data['enrollment_type'],
            'status'          => 'enrolled',
            'enrollment_date' => $data['enrollment_date'],
            'notes'           => $data['notes'],
            'encoded_by'      => Auth::id(),
        ]);

        return back()->with('success', 'Student enrolled successfully.');
    }

    // ── Bulk enroll via CSV (JSON array posted as JSON body) ──────────────────

    public function bulkStore(Request $request): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $data = $request->validate([
            'school_year_id'  => ['required', 'integer', 'exists:school_years,id'],
            'section_id'      => ['required', 'integer'],
            'enrollment_type' => ['required', Rule::in(['new', 'returning', 'transferee', 'returnee'])],
            'enrollment_date' => ['required', 'date'],
            'pisays_ids'      => ['required', 'array', 'min:1', 'max:60'],
            'pisays_ids.*'    => ['required', 'string'],
        ]);

        $section = Section::where('id', $data['section_id'])
            ->where('school_year_id', $data['school_year_id'])
            ->firstOrFail();

        $students = Student::whereIn('pisaysystemID', $data['pisays_ids'])
            ->get(['id', 'pisaysystemID']);

        $alreadyEnrolledIds = StudentEnrollment::where('school_year_id', $data['school_year_id'])
            ->whereIn('status', ['enrolled', 'on_leave'])
            ->pluck('student_id')
            ->flip();

        $enrolled = 0;
        $skipped  = 0;

        DB::transaction(function () use ($students, $alreadyEnrolledIds, $data, $section, &$enrolled, &$skipped) {
            foreach ($students as $student) {
                if ($alreadyEnrolledIds->has($student->id)) {
                    $skipped++;
                    continue;
                }

                StudentEnrollment::create([
                    'student_id'      => $student->id,
                    'school_year_id'  => $data['school_year_id'],
                    'section_id'      => $section->id,
                    'grade_level'     => $section->levelid,
                    'enrollment_type' => $data['enrollment_type'],
                    'status'          => 'enrolled',
                    'enrollment_date' => $data['enrollment_date'],
                    'encoded_by'      => Auth::id(),
                ]);

                $enrolled++;
            }
        });

        $message = "{$enrolled} student(s) enrolled.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (already enrolled).";
        }

        return back()->with('success', $message);
    }

    // ── Update enrollment (status / section transfer) ─────────────────────────

    public function update(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $data = $request->validate([
            'status'  => ['required', Rule::in(['enrolled', 'dropped', 'transferred_out', 'on_leave', 'completed'])],
            'notes'   => ['nullable', 'string', 'max:500'],
            'section_id' => ['nullable', 'integer'],
        ]);

        // If transferring to a different section within the same school year
        if (! empty($data['section_id']) && $data['section_id'] !== $enrollment->section_id) {
            $newSection = Section::where('id', $data['section_id'])
                ->where('school_year_id', $enrollment->school_year_id)
                ->firstOrFail();

            $count = StudentEnrollment::where('section_id', $newSection->id)
                ->where('school_year_id', $enrollment->school_year_id)
                ->where('status', 'enrolled')
                ->count();

            abort_if($count >= $newSection->capacity, 422, 'Target section is at capacity.');

            $enrollment->section_id  = $newSection->id;
            $enrollment->grade_level = $newSection->levelid;
        }

        $enrollment->status = $data['status'];
        $enrollment->notes  = $data['notes'];
        $enrollment->save();

        return back()->with('success', 'Enrollment updated.');
    }

    // ── Drop / cancel enrollment ──────────────────────────────────────────────

    public function destroy(StudentEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $enrollment->update(['status' => 'dropped']);

        return back()->with('success', 'Enrollment dropped.');
    }
}
