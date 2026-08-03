<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\AMS\ActivityStudentAttendance;
use App\Models\CID\CompetitionParticipant;
use App\Models\Consultation;
use App\Models\Discipline\DisciplineCase;
use App\Models\GuidanceConsultation;
use App\Models\HomeroomAttendance\AttendanceRecord;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentEnrollment;
use App\Models\ResidenceHall\RhIntern;
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
        return 'Returns a comprehensive profile for one student: personal/contact details (birthday, sex, '
             . 'blood type, contact number, address, LRN), academic record/GWA, homeroom and gate '
             . 'attendance, discipline cases, library activity, competitions, full enrollment history, '
             . 'current section, guardian contact, clinic/health consultation records, guidance/counseling '
             . 'referral records, activity (AMS) participation, and residence hall/dormer assignment — each '
             . 'section only included if the requesting user has access to it. Use for open-ended "tell me '
             . 'about student X" questions (including birthdate, contact number, address, or other personal '
             . 'details); use get_student_info instead for a quick single-fact lookup.';
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
            'personal_info' => [
                'name' => trim("{$student->firstname} {$student->middlename} {$student->lastname}"),
                'birthday' => $student->birthday,
                'sex' => $student->sex,
                'blood_type' => $student->bloodtype,
                'contact_no' => $student->contactno1,
                'lrn' => $student->lrn,
                'dormer' => $student->dormer,
                'address' => implode(', ', array_filter([$student->houseno, $student->barangay, $student->municipal, $student->province])),
            ],
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

        if ($user->hasPermission('health.manage')) {
            $profile['health_records'] = Consultation::where('student_id', $student->id)
                ->latest('id')->limit(10)
                ->get(['reason', 'status', 'scheduled_at', 'date_attended', 'notes'])->toArray();
        }

        if ($user->hasPermission('guidance.view')) {
            // requestor_id is a legacy ambiguous FK (can point to students or users) — matches
            // the same students-table-first resolution GuidanceConsultationController uses.
            $profile['guidance_records'] = GuidanceConsultation::where('requestor_id', $student->id)
                ->latest('id')->limit(10)
                ->get(['concern', 'status', 'consultation_type', 'date_time_preferred', 'intervention'])->toArray();
        }

        if ($user->hasAnyPermission(['activities.manage', 'activities.view_all', 'activities.monitor', 'activities.evaluation_committee'])) {
            $profile['ams_participation'] = ActivityStudentAttendance::with('activity')->where('participant_id', $student->id)
                ->latest('id')->limit(10)->get()
                ->map(fn (ActivityStudentAttendance $a) => ['activity_title' => $a->activity?->title, 'attended' => $a->attended, 'hours_attended' => $a->hours_attended])
                ->values()->toArray();
        }

        if ($user->hasAnyPermission(['rh.interns.view', 'rh.interns.manage'])) {
            $intern = RhIntern::with('room')->where('student_id', $student->id)->latest('id')->first();
            if ($intern) {
                $profile['residence_hall'] = [
                    'residence_hall' => $intern->room?->residence_hall,
                    'room_number' => $intern->room?->room_number,
                    'bed_number' => $intern->bed_number,
                    'status' => $intern->status,
                    'check_in_date' => $intern->check_in_date,
                    'check_out_date' => $intern->check_out_date,
                ];
            }
        }

        return $profile;
    }
}
