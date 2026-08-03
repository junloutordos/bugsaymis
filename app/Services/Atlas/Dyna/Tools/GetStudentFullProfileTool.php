<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\CID\CompetitionParticipant;
use App\Models\Discipline\DisciplineCase;
use App\Models\HomeroomAttendance\AttendanceRecord;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentEnrollment;
use App\Models\StudentAttendance\StudentAttendanceLog;
use App\Models\User;
use App\Services\Registrar\StudentTranscriptService;
use App\Services\StudentSectionResolver;

class GetStudentFullProfileTool implements DynaTool
{
    public function __construct(private readonly StudentTranscriptService $transcripts) {}

    public function name(): string { return 'get_student_full_profile'; }

    public function description(): string
    {
        return 'Returns a comprehensive profile for one student: academic record/GWA, homeroom and gate '
             . 'attendance, discipline cases, library activity, competitions, full enrollment history, '
             . 'current section, and guardian contact — each section only included if the requesting user '
             . 'has access to it. Use for open-ended "tell me about student X" questions; use '
             . 'get_student_info instead for a quick single-fact lookup.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['identifier'],
            'properties' => [
                'identifier' => ['type' => 'string', 'description' => 'Student name or system ID.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->hasPermission('students.enrollment.view')) {
            throw new \RuntimeException('This account does not have student enrollment access.');
        }

        $student = \DB::table('students')
            ->where('lastname', 'like', '%'.$input['identifier'].'%')
            ->orWhere('firstname', 'like', '%'.$input['identifier'].'%')
            ->orWhere('pisaysystemID', $input['identifier'])
            ->first();

        if (! $student) {
            return ['note' => "No student found matching \"{$input['identifier']}\"."];
        }

        $currentSchoolYearId = \App\Models\FacultyLoading\SchoolYear::where('is_current', true)->value('id');

        $profile = [
            'enrollment_history' => StudentEnrollment::where('student_id', $student->id)->orderByDesc('school_year_id')->get()
                ->map(fn (StudentEnrollment $e) => ['school_year_id' => $e->school_year_id, 'grade_level' => $e->grade_level, 'status' => $e->status])->values()->toArray(),
        ];

        $section = (new StudentSectionResolver())->latestForStudent($student->id);
        if ($section) {
            $profile['current_section'] = ['grade_level' => $section->levelid, 'section_id' => $section->sectionid];
        }

        $guardians = \DB::table('student_parent_contact')
            ->join('parent_contacts', 'parent_contacts.id', '=', 'student_parent_contact.parent_contact_id')
            ->where('student_parent_contact.student_id', $student->id)
            ->get(['parent_contacts.name', 'parent_contacts.email', 'parent_contacts.mobile_phone', 'student_parent_contact.relationship']);
        if ($guardians->isNotEmpty()) {
            $profile['guardian_contact'] = $guardians->toArray();
        }

        if ($currentSchoolYearId && $user->hasPermission('class-records.view')) {
            $profile['academic_record'] = $this->transcripts->getTranscript($student->id, $currentSchoolYearId);

            $standing = StudentAcademicStanding::where('student_id', $student->id)->where('school_year_id', $currentSchoolYearId)->first();
            if ($standing) {
                $profile['gwa'] = $standing->gwa;
            }
        }

        if ($user->hasPermission('homeroom-attendance.admin')) {
            $profile['attendance_homeroom'] = AttendanceRecord::with('attendanceDate')->where('student_id', $student->id)
                ->latest('id')->limit(10)->get()
                ->map(fn (AttendanceRecord $r) => ['date' => $r->attendanceDate?->date, 'status' => $r->status])->values()->toArray();
        }

        if ($user->hasPermission('students.attendance.view')) {
            $profile['attendance_gate'] = StudentAttendanceLog::where('student_id', $student->id)
                ->latest('scan_time')->limit(10)->get(['scan_time', 'type', 'gate_location'])->toArray();
        }

        if ($user->hasPermission('discipline.view')) {
            $profile['discipline'] = DisciplineCase::where('student_id', $student->id)
                ->get(['case_no', 'status', 'threat_level', 'nature_of_offense'])->toArray();
        }

        // borrower_type stores the literal lowercase string 'student' (confirmed via
        // LibraryBorrowingsController), not a class-name morph map.
        $profile['library'] = \App\Models\Borrowing::where('borrower_type', 'student')->where('borrower_id', $student->id)
            ->get(['status', 'due_date'])->toArray();

        if ($user->hasPermission('cid.competitions.manage')) {
            $profile['competitions'] = CompetitionParticipant::with('competition')->where('student_id', $student->id)
                ->get()->map(fn (CompetitionParticipant $c) => ['competition' => $c->competition?->name, 'role' => $c->role, 'award' => $c->award])->values()->toArray();
        }

        return $profile;
    }
}
