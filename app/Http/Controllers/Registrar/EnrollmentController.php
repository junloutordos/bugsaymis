<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\StudentClearance\StudentClearance;
use App\Models\StudentClearance\StudentClearancePeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        // Enrolled but not yet placed in a section ("pending"), per grade level
        $pendingByGrade = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->whereNull('section_id')
            ->selectRaw('grade_level, COUNT(*) as count')
            ->groupBy('grade_level')
            ->pluck('count', 'grade_level');

        return Inertia::render('Registrar/Enrollment/Index', [
            'sections'           => $sectionData,
            'schoolYears'        => $schoolYears,
            'selectedSchoolYear' => $schoolYearId,
            'gradeLevels'        => range(7, 12),
            'pendingByGrade'     => $pendingByGrade,
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

        $clearancePeriod = StudentClearancePeriod::where('school_year_id', $schoolYearId)
            ->orderByRaw("FIELD(status, 'open', 'draft', 'closed', 'archived')")
            ->latest('id')
            ->first();

        $clearances = $clearancePeriod
            ? StudentClearance::where('student_clearance_period_id', $clearancePeriod->id)
                ->whereIn('student_id', $studentIds)
                ->with('items:id,student_clearance_id,status')
                ->get()
                ->keyBy('student_id')
            : collect();

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
            'clearance_status' => $clearances->get($e->student_id)?->status,
            'clearance_progress' => $clearances->get($e->student_id) ? [
                'done'  => $clearances->get($e->student_id)->items->whereIn('status', ['cleared', 'waived', 'not_applicable'])->count(),
                'total' => $clearances->get($e->student_id)->items->count(),
            ] : null,
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
            ->where('status', 'Enrolled')
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

    // ── Unassigned students for a grade level (pending section placement) ─────

    public function unassigned(Request $request): JsonResponse
    {
        $this->authorize('students.enrollment.view');

        $schoolYearId = (int) $request->input('school_year_id',
            SchoolYear::where('is_current', true)->value('id'));
        $gradeLevel = (int) $request->input('grade_level');

        $enrollments = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->where('grade_level', $gradeLevel)
            ->whereNull('section_id')
            ->get();

        $studentIds = $enrollments->pluck('student_id')->unique()->values();
        $students   = Student::whereIn('id', $studentIds)
            ->get(['id', 'firstname', 'lastname', 'middlename', 'pisaysystemID', 'lrn', 'sex'])
            ->keyBy('id');

        $result = $enrollments->map(fn ($e) => [
            'enrollment_id'   => $e->id,
            'student_id'      => $e->student_id,
            'full_name'       => $students->get($e->student_id)?->full_name ?? 'Unknown',
            'pisays_id'       => $students->get($e->student_id)?->pisaysystemID,
            'lrn'             => $students->get($e->student_id)?->lrn,
            'sex'             => $students->get($e->student_id)?->sex,
            'enrollment_type' => $e->enrollment_type,
        ])->sortBy('full_name')->values();

        return response()->json($result);
    }

    // ── Continuing students from last year, not yet enrolled this SY ──────────

    public function continuingStudents(Request $request): JsonResponse
    {
        $this->authorize('students.enrollment.manage');

        $schoolYearId  = (int) $request->input('school_year_id',
            SchoolYear::where('is_current', true)->value('id'));
        $gradeLevel    = (int) $request->input('grade_level');
        $previousLevel = $gradeLevel - 1;

        // Already has an enrollment record for this SY (any status) — exclude
        $alreadyEnrolledIds = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->pluck('student_id');

        $students = DB::table('section_students as ss')
            ->join('students as s', 's.id', '=', 'ss.studentid')
            ->where('ss.syid', 12)
            ->where('s.status', 'Enrolled')
            ->where('ss.levelid', $previousLevel)
            ->whereNotIn('s.id', $alreadyEnrolledIds)
            ->select('s.id', 's.lastname', 's.firstname', 's.middlename', 's.pisaysystemID', 's.sex')
            ->distinct()
            ->orderBy('s.lastname')
            ->orderBy('s.firstname')
            ->get();

        return response()->json($students->map(fn ($s) => [
            'id'        => $s->id,
            'full_name' => trim("{$s->lastname}, {$s->firstname}" . ($s->middlename ? " {$s->middlename}" : '')),
            'pisays_id' => $s->pisaysystemID,
            'sex'       => $s->sex,
        ]));
    }

    // ── Enroll a student ──────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $data = $request->validate([
            'student_id'       => ['required', 'integer'],
            'school_year_id'   => ['required', 'integer', 'exists:school_years,id'],
            'grade_level'      => ['required', 'integer', 'between:7,12'],
            'enrollment_type'  => ['required', Rule::in(['new', 'returning', 'transferee', 'returnee'])],
            'enrollment_date'  => ['required', 'date'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ]);

        // Verify student exists
        abort_unless(Student::where('id', $data['student_id'])->exists(), 404, 'Student not found.');

        // Guard: already enrolled this school year
        abort_if(
            StudentEnrollment::where('student_id', $data['student_id'])
                ->where('school_year_id', $data['school_year_id'])
                ->whereIn('status', ['enrolled', 'on_leave'])
                ->exists(),
            422,
            'Student is already enrolled for this school year.'
        );

        // Section is assigned separately, after enrollment (see Assign by Grade List).
        StudentEnrollment::create([
            'student_id'      => $data['student_id'],
            'school_year_id'  => $data['school_year_id'],
            'section_id'      => null,
            'grade_level'     => $data['grade_level'],
            'enrollment_type' => $data['enrollment_type'],
            'status'          => 'enrolled',
            'enrollment_date' => $data['enrollment_date'],
            'notes'           => $data['notes'],
            'encoded_by'      => Auth::id(),
        ]);

        return back()->with('success', 'Student enrolled successfully. Assign their section from "Assign by Grade List".');
    }

    // ── Bulk enroll via CSV (JSON array posted as JSON body) ──────────────────

    public function bulkStore(Request $request): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $data = $request->validate([
            'school_year_id'  => ['required', 'integer', 'exists:school_years,id'],
            'grade_level'     => ['required', 'integer', 'between:7,12'],
            'enrollment_type' => ['required', Rule::in(['new', 'returning', 'transferee', 'returnee'])],
            'enrollment_date' => ['required', 'date'],
            'pisays_ids'      => ['required', 'array', 'min:1', 'max:200'],
            'pisays_ids.*'    => ['required', 'string'],
        ]);

        $students = Student::whereIn('pisaysystemID', $data['pisays_ids'])
            ->where('status', 'Enrolled')
            ->get(['id', 'pisaysystemID']);

        $alreadyEnrolledIds = StudentEnrollment::where('school_year_id', $data['school_year_id'])
            ->whereIn('status', ['enrolled', 'on_leave'])
            ->pluck('student_id')
            ->flip();

        $enrolled = 0;
        $skipped  = 0;

        // Section is assigned separately, after enrollment (see Assign by Grade List).
        DB::transaction(function () use ($students, $alreadyEnrolledIds, $data, &$enrolled, &$skipped) {
            foreach ($students as $student) {
                if ($alreadyEnrolledIds->has($student->id)) {
                    $skipped++;
                    continue;
                }

                StudentEnrollment::create([
                    'student_id'      => $student->id,
                    'school_year_id'  => $data['school_year_id'],
                    'section_id'      => null,
                    'grade_level'     => $data['grade_level'],
                    'enrollment_type' => $data['enrollment_type'],
                    'status'          => 'enrolled',
                    'enrollment_date' => $data['enrollment_date'],
                    'encoded_by'      => Auth::id(),
                ]);

                $enrolled++;
            }
        });

        $notFound = count($data['pisays_ids']) - $students->count();

        $message = "{$enrolled} student(s) enrolled.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (already enrolled).";
        }
        if ($notFound > 0) {
            $message .= " {$notFound} PISAY ID(s) not found or not an active student.";
        }
        if ($enrolled > 0) {
            $message .= ' Assign their sections from "Assign by Grade List".';
        }

        return back()->with('success', $message);
    }

    // ── Bulk-assign pending students to a section (checkbox picker) ───────────

    public function bulkAssignSection(Request $request): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $data = $request->validate([
            'school_year_id'   => ['required', 'integer', 'exists:school_years,id'],
            'section_id'       => ['required', 'integer'],
            'enrollment_ids'   => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['required', 'integer'],
        ]);

        $section = Section::where('id', $data['section_id'])
            ->where('school_year_id', $data['school_year_id'])
            ->firstOrFail();

        $enrollments = StudentEnrollment::whereIn('id', $data['enrollment_ids'])
            ->where('school_year_id', $data['school_year_id'])
            ->whereNull('section_id')
            ->get();

        abort_if(
            $enrollments->count() !== count($data['enrollment_ids']),
            422,
            'One or more selected students are no longer available for assignment.'
        );

        abort_if(
            $enrollments->firstWhere('grade_level', '!=', $section->levelid),
            422,
            "Selected students do not match {$section->sectionname}'s grade level."
        );

        $currentCount = StudentEnrollment::where('section_id', $section->id)
            ->where('school_year_id', $data['school_year_id'])
            ->where('status', 'enrolled')
            ->count();

        abort_if(
            $currentCount + $enrollments->count() > $section->capacity,
            422,
            "Assigning these students would exceed {$section->sectionname}'s capacity ({$section->capacity})."
        );

        DB::transaction(function () use ($enrollments, $section) {
            foreach ($enrollments as $enrollment) {
                $enrollment->update(['section_id' => $section->id]);

                // Keep legacy section consumers (AMS, consultations, library, and
                // the Faculty Loading section roster) aligned with enrollment.
                if ($section->syid !== null) {
                    DB::table('section_students')->updateOrInsert(
                        ['studentid' => $enrollment->student_id, 'syid' => $section->syid],
                        ['sectionid' => $section->id, 'levelid' => $section->levelid],
                    );
                }
            }
        });

        $count = $enrollments->count();

        return back()->with('success', "{$count} student(s) assigned to {$section->sectionname}.");
    }

    // ── Update enrollment status ──────────────────────────────────────────────

    public function update(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $data = $request->validate([
            'status'  => ['required', Rule::in(['enrolled', 'dropped', 'transferred_out', 'on_leave', 'completed'])],
            'notes'   => ['nullable', 'string', 'max:500'],
        ]);

        $enrollment->status = $data['status'];
        $enrollment->notes  = $data['notes'];
        $enrollment->save();

        return back()->with('success', 'Enrollment updated.');
    }

    // ── Transfer an enrolled student to another section ──────────────────────

    public function transferSection(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $data = $request->validate([
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ]);

        if ($enrollment->status !== 'enrolled') {
            throw ValidationException::withMessages([
                'section_id' => ['Only enrolled students can be transferred between sections.'],
            ]);
        }
        if ((int) $data['section_id'] === (int) $enrollment->section_id) {
            throw ValidationException::withMessages([
                'section_id' => ['Select a different target section.'],
            ]);
        }

        $sourceSection = $enrollment->section;

        $targetSection = DB::transaction(function () use ($data, $enrollment) {
            $target = Section::where('id', $data['section_id'])
                ->where('school_year_id', $enrollment->school_year_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $target) {
                throw ValidationException::withMessages([
                    'section_id' => ['Target section must be active and belong to the same school year.'],
                ]);
            }
            if ((int) $target->levelid !== (int) $enrollment->grade_level) {
                throw ValidationException::withMessages([
                    'section_id' => ['Target section must be in the same grade level.'],
                ]);
            }

            $currentCount = StudentEnrollment::where('section_id', $target->id)
                ->where('school_year_id', $enrollment->school_year_id)
                ->where('status', 'enrolled')
                ->count();

            if ($currentCount >= $target->capacity) {
                throw ValidationException::withMessages([
                    'section_id' => ['Target section is at capacity.'],
                ]);
            }

            $enrollment->update([
                'section_id' => $target->id,
                'grade_level' => $target->levelid,
            ]);

            // Keep legacy section consumers (AMS, consultations, library, and
            // the Faculty Loading section roster) aligned with enrollment.
            if ($target->syid !== null) {
                DB::table('section_students')->updateOrInsert(
                    ['studentid' => $enrollment->student_id, 'syid' => $target->syid],
                    ['sectionid' => $target->id, 'levelid' => $target->levelid],
                );
            }

            // Clearance queues and reports store a section snapshot linked to
            // this enrollment, so move that snapshot with the student.
            StudentClearance::where('student_enrollment_id', $enrollment->id)->update([
                'section_id' => $target->id,
                'grade_level' => $target->levelid,
                'adviser_id' => $target->adviserUser()->value('users.id'),
            ]);

            return $target;
        });

        $sourceName = $sourceSection?->sectionname ?? 'the previous section';

        return back()->with(
            'success',
            "Student transferred from {$sourceName} to {$targetSection->sectionname}."
        );
    }

    // ── Drop / cancel enrollment ──────────────────────────────────────────────

    public function destroy(StudentEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $enrollment->update(['status' => 'dropped']);

        return back()->with('success', 'Enrollment dropped.');
    }
}
