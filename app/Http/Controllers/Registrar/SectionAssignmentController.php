<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SectionAssignmentController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): Response
    {
        $this->authorize('students.enrollment.manage');

        $currentSyId = SchoolYear::where('is_current', true)->value('id');

        // Load sections for current SY, grouped by grade level
        $sections = Section::where('school_year_id', $currentSyId)
            ->where('is_active', true)
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get(['id', 'levelid', 'sectionname', 'capacity'])
            ->groupBy('levelid')
            ->map(fn ($rows) => $rows->values());

        // ── Source 1: legacy section_students (grades 8-12, not yet enrolled) ─
        // Only pull if no grade 8-12 enrollments exist yet for this SY.
        $legacyDone = StudentEnrollment::where('school_year_id', $currentSyId)
            ->where('grade_level', '>=', 8)
            ->exists();

        $legacyStudents = collect();

        if (! $legacyDone) {
            $legacyStudents = DB::table('section_students as ss')
                ->join('students as s', 's.id', '=', 'ss.studentid')
                ->where('ss.syid', 12)
                ->where('s.status', 'Enrolled')
                ->where('ss.levelid', '<', 12)
                ->select(
                    's.id as student_id',
                    's.lastname',
                    's.firstname',
                    's.middlename',
                    's.sex',
                    's.pisaysystemID',
                    DB::raw('ss.levelid + 1 as new_grade_level'),
                )
                ->orderByRaw('new_grade_level')
                ->orderBy('s.lastname')
                ->orderBy('s.firstname')
                ->distinct()
                ->get()
                ->map(fn ($r) => [
                    'student_id'      => $r->student_id,
                    'full_name'       => trim("{$r->lastname}, {$r->firstname}" . ($r->middlename ? " {$r->middlename}" : '')),
                    'sex'             => $r->sex,
                    'pisays_id'       => $r->pisaysystemID,
                    'new_grade_level' => (int) $r->new_grade_level,
                    'source'          => 'legacy',
                ]);
        }

        // ── Source 2: approved enrollment stubs with no section yet ───────────
        // Grade 7 (and any other grade) students enrolled but section_id = null.
        $stubEnrollments = StudentEnrollment::where('school_year_id', $currentSyId)
            ->whereNull('section_id')
            ->get(['student_id', 'grade_level']);

        $stubStudentIds = $stubEnrollments->pluck('student_id');
        $stubGradeMap   = $stubEnrollments->keyBy('student_id');

        $stubStudents = collect();
        if ($stubStudentIds->isNotEmpty()) {
            $stubStudents = DB::table('students')
                ->whereIn('id', $stubStudentIds)
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get(['id', 'lastname', 'firstname', 'middlename', 'sex', 'pisaysystemID'])
                ->map(fn ($s) => [
                    'student_id'      => $s->id,
                    'full_name'       => trim("{$s->lastname}, {$s->firstname}" . ($s->middlename ? " {$s->middlename}" : '')),
                    'sex'             => $s->sex,
                    'pisays_id'       => $s->pisaysystemID,
                    'new_grade_level' => (int) $stubGradeMap[$s->id]->grade_level,
                    'source'          => 'stub',
                ]);
        }

        // Merge and group by grade level
        $students = $legacyStudents
            ->merge($stubStudents)
            ->groupBy('new_grade_level')
            ->map(fn ($rows) => $rows->values())
            ->sortKeys();

        $alreadyDone = $students->isEmpty();

        return Inertia::render('Registrar/SectionAssignment/Index', [
            'students'    => $students,
            'sections'    => $sections,
            'alreadyDone' => $alreadyDone,
            'currentSyId' => $currentSyId,
        ]);
    }

    // ── Store (assign sections) ───────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $currentSyId = SchoolYear::where('is_current', true)->value('id');

        $data = $request->validate([
            'assignments'              => ['required', 'array', 'min:1'],
            'assignments.*.student_id' => ['required', 'integer'],
            'assignments.*.section_id' => ['required', 'integer', 'exists:sections,id'],
        ]);

        // Build section lookup for current SY
        $sectionIds = collect($data['assignments'])->pluck('section_id')->unique();
        $sections   = Section::whereIn('id', $sectionIds)
            ->where('school_year_id', $currentSyId)
            ->get()
            ->keyBy('id');

        foreach ($sectionIds as $sid) {
            abort_unless($sections->has($sid), 422, "Section {$sid} does not belong to the current school year.");
        }

        // Count capacity usage per section
        $sectionCounts = collect($data['assignments'])->countBy('section_id');
        foreach ($sectionCounts as $sid => $count) {
            $cap = $sections[$sid]->capacity;
            abort_if($count > $cap, 422,
                "Section {$sections[$sid]->sectionname} would exceed capacity ({$count} assigned, capacity {$cap}).");
        }

        // Pre-load existing enrollment stubs (null section) for this SY
        $stubs = StudentEnrollment::where('school_year_id', $currentSyId)
            ->whereNull('section_id')
            ->get()
            ->keyBy('student_id');

        // Derive grade levels from legacy data (for non-stub students)
        $legacyGrades = DB::table('section_students as ss')
            ->join('students as s', 's.id', '=', 'ss.studentid')
            ->where('ss.syid', 12)
            ->where('s.status', 'Enrolled')
            ->where('ss.levelid', '<', 12)
            ->selectRaw('s.id as student_id, ss.levelid + 1 as new_grade_level')
            ->distinct()
            ->get()
            ->keyBy('student_id');

        $enrollmentDate = now()->toDateString();
        $encodedBy      = Auth::id();

        DB::transaction(function () use (
            $data, $sections, $stubs, $legacyGrades,
            $currentSyId, $enrollmentDate, $encodedBy
        ) {
            foreach ($data['assignments'] as $row) {
                $studentId = $row['student_id'];
                $sectionId = $row['section_id'];
                $section   = $sections[$sectionId];

                if ($stubs->has($studentId)) {
                    // Grade 7 (or any stub): update existing enrollment with section
                    $stubs[$studentId]->update([
                        'section_id'  => $sectionId,
                        'grade_level' => $section->levelid,
                    ]);
                } else {
                    // Legacy grades 8-12: create new enrollment
                    $legacy     = $legacyGrades->get($studentId);
                    $gradeLevel = $legacy ? (int) $legacy->new_grade_level : $section->levelid;

                    abort_unless(
                        $section->levelid === $gradeLevel,
                        422,
                        "Student {$studentId} assigned to wrong grade section."
                    );

                    StudentEnrollment::create([
                        'student_id'      => $studentId,
                        'school_year_id'  => $currentSyId,
                        'section_id'      => $sectionId,
                        'grade_level'     => $section->levelid,
                        'enrollment_type' => 'returning',
                        'status'          => 'enrolled',
                        'enrollment_date' => $enrollmentDate,
                        'encoded_by'      => $encodedBy,
                    ]);
                }
            }
        });

        $count = count($data['assignments']);

        return redirect()
            ->route('registrar.enrollment.index')
            ->with('success', "{$count} students assigned to sections successfully.");
    }
}
