<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
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

        // Guard: if enrollments already exist for the current SY, nothing left to import
        $alreadyDone = StudentEnrollment::where('school_year_id', $currentSyId)->exists();

        // Load sections for current SY, grouped by grade level
        $sections = Section::where('school_year_id', $currentSyId)
            ->where('is_active', true)
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get(['id', 'levelid', 'sectionname', 'capacity'])
            ->groupBy('levelid')
            ->map(fn ($rows) => $rows->values());

        // Load students from legacy section_students syid=12:
        //   - status = 'Enrolled' only (excludes Inactive, Graduated, Grade13)
        //   - levelid < 12 (grade 12 in SY 2025-2026 are graduating; excluded)
        //   - DISTINCT by studentid to guard against duplicate section_students rows
        $rawRows = DB::table('section_students as ss')
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
            ->orderBy('ss.levelid')
            ->orderBy('s.lastname')
            ->orderBy('s.firstname')
            ->distinct()
            ->get();

        // Group by new_grade_level; build name helper inline
        $students = $rawRows
            ->map(fn ($r) => [
                'student_id'      => $r->student_id,
                'full_name'       => trim("{$r->lastname}, {$r->firstname}" . ($r->middlename ? " {$r->middlename}" : '')),
                'sex'             => $r->sex,
                'pisays_id'       => $r->pisaysystemID,
                'new_grade_level' => (int) $r->new_grade_level,
            ])
            ->groupBy('new_grade_level')
            ->map(fn ($rows) => $rows->values());

        return Inertia::render('Registrar/SectionAssignment/Index', [
            'students'    => $students,
            'sections'    => $sections,
            'alreadyDone' => $alreadyDone,
            'currentSyId' => $currentSyId,
        ]);
    }

    // ── Store (bulk create enrollments) ───────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $currentSyId = SchoolYear::where('is_current', true)->value('id');

        // Guard: prevent double-import
        if (StudentEnrollment::where('school_year_id', $currentSyId)->exists()) {
            return back()->with('error', 'Enrollment records already exist for this school year.');
        }

        $data = $request->validate([
            'assignments'              => ['required', 'array', 'min:1'],
            'assignments.*.student_id' => ['required', 'integer'],
            'assignments.*.section_id' => ['required', 'integer', 'exists:sections,id'],
        ]);

        // Build section lookup: id → section row (for grade_level validation + capacity)
        $sectionIds = collect($data['assignments'])->pluck('section_id')->unique();
        $sections   = Section::whereIn('id', $sectionIds)
            ->where('school_year_id', $currentSyId)
            ->get()
            ->keyBy('id');

        // Validate all requested section IDs belong to current SY
        foreach ($sectionIds as $sid) {
            abort_unless($sections->has($sid), 422, "Section {$sid} does not belong to the current school year.");
        }

        // Derive expected new grade levels from legacy data (same query as index)
        $legacyGrades = DB::table('section_students as ss')
            ->join('students as s', 's.id', '=', 'ss.studentid')
            ->where('ss.syid', 12)
            ->where('s.status', 'Enrolled')
            ->where('ss.levelid', '<', 12)
            ->selectRaw('s.id as student_id, ss.levelid + 1 as new_grade_level')
            ->distinct()
            ->get()
            ->keyBy('student_id');

        // Count capacity usage per section from submitted assignments
        $sectionCounts = collect($data['assignments'])->countBy('section_id');

        foreach ($sectionCounts as $sid => $count) {
            $cap = $sections[$sid]->capacity;
            abort_if($count > $cap, 422,
                "Section {$sections[$sid]->sectionname} would exceed capacity ({$count} assigned, capacity {$cap}).");
        }

        $enrollmentDate = now()->toDateString();
        $encodedBy      = Auth::id();

        DB::transaction(function () use ($data, $sections, $legacyGrades, $currentSyId, $enrollmentDate, $encodedBy) {
            foreach ($data['assignments'] as $row) {
                $studentId = $row['student_id'];
                $sectionId = $row['section_id'];
                $section   = $sections[$sectionId];
                $legacy    = $legacyGrades->get($studentId);

                // Validate grade level: section's levelid must match expected new grade
                if ($legacy) {
                    abort_unless(
                        $section->levelid === (int) $legacy->new_grade_level,
                        422,
                        "Student {$studentId} assigned to wrong grade section."
                    );
                }

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
        });

        $count = count($data['assignments']);

        return redirect()
            ->route('registrar.enrollment.index')
            ->with('success', "{$count} students enrolled for SY 2026-2027 successfully.");
    }
}
