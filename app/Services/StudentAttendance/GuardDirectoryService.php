<?php

namespace App\Services\StudentAttendance;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\HR\EmployeeSchedule;
use App\Models\Student;
use App\Models\StudentAttendance\StudentAttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GuardDirectoryService
{
    public function searchStudents(string $search): Collection
    {
        $schoolYearId = SchoolYear::where('is_current', true)->value('id');
        if (! $schoolYearId) {
            return collect();
        }

        $needle = trim($search);

        return DB::table('students as students')
            ->join('student_enrollments as enrollment', function ($join) use ($schoolYearId) {
                $join->on('enrollment.student_id', '=', 'students.id')
                    ->where('enrollment.school_year_id', $schoolYearId)
                    ->where('enrollment.status', 'enrolled');
            })
            ->leftJoin('sections as section', 'section.id', '=', 'enrollment.section_id')
            ->where(function ($query) use ($needle) {
                $query->where('students.pisaysystemID', $needle)
                    ->orWhere('students.lastname', 'like', $needle.'%')
                    ->orWhere('students.firstname', 'like', $needle.'%')
                    ->orWhereRaw("CONCAT_WS(' ', students.firstname, students.middlename, students.lastname) LIKE ?", ['%'.$needle.'%'])
                    ->orWhereRaw("CONCAT_WS(' ', students.lastname, students.firstname) LIKE ?", ['%'.$needle.'%']);
            })
            ->orderBy('students.lastname')
            ->orderBy('students.firstname')
            ->limit(10)
            ->get([
                'students.id',
                'students.lastname',
                'students.firstname',
                'students.middlename',
                'students.nickname',
                'students.pisaysystemID',
                'students.img',
                'enrollment.grade_level',
                'section.sectionname',
            ])
            ->map(fn ($student) => [
                'id' => (int) $student->id,
                'name' => $this->studentName($student),
                'nickname' => $student->nickname ?: null,
                'student_number' => $student->pisaysystemID ?: null,
                'grade_level' => (int) $student->grade_level,
                'section' => $student->sectionname ?: null,
                'photo_url' => $student->img ? route('student-attendance.student.photo', $student->id) : null,
            ]);
    }

    public function studentDetail(int $studentId): ?array
    {
        $schoolYear = SchoolYear::where('is_current', true)->first(['id', 'name']);
        if (! $schoolYear) {
            return null;
        }

        $student = DB::table('students as students')
            ->join('student_enrollments as enrollment', function ($join) use ($schoolYear) {
                $join->on('enrollment.student_id', '=', 'students.id')
                    ->where('enrollment.school_year_id', $schoolYear->id)
                    ->where('enrollment.status', 'enrolled');
            })
            ->leftJoin('sections as section', 'section.id', '=', 'enrollment.section_id')
            ->leftJoin('users as adviser', 'adviser.id', '=', 'section.adviser')
            ->where('students.id', $studentId)
            ->first([
                'students.id',
                'students.lastname',
                'students.firstname',
                'students.middlename',
                'students.nickname',
                'students.sex',
                'students.pisaysystemID',
                'students.img',
                'enrollment.section_id',
                'enrollment.grade_level',
                'section.sectionname',
                'adviser.id as adviser_id',
                'adviser.name as adviser_name',
            ]);

        if (! $student) {
            return null;
        }

        $contacts = DB::table('student_parent_contact as link')
            ->join('parent_contacts as contact', 'contact.id', '=', 'link.parent_contact_id')
            ->where('link.student_id', $studentId)
            ->orderBy('contact.name')
            ->get(['contact.id', 'contact.name', 'contact.mobile_phone', 'contact.email', 'link.relationship']);

        $schedule = $this->sectionSchedule((int) $student->section_id, (int) $schoolYear->id);
        $latestGateLog = StudentAttendanceLog::where('student_id', $studentId)
            ->latest('scan_time')
            ->first(['type', 'scan_time', 'gate_location']);

        return [
            'id' => (int) $student->id,
            'name' => $this->studentName($student),
            'nickname' => $student->nickname ?: null,
            'sex' => $student->sex ?: null,
            'student_number' => $student->pisaysystemID ?: null,
            'photo_url' => $student->img ? route('student-attendance.student.photo', $student->id) : null,
            'school_year' => $schoolYear->name,
            'grade_level' => (int) $student->grade_level,
            'section' => $student->sectionname ?: null,
            'adviser' => $student->adviser_name ? [
                'id' => (int) $student->adviser_id,
                'name' => $student->adviser_name,
            ] : null,
            'parents' => $contacts->map(fn ($contact) => [
                'id' => (int) $contact->id,
                'name' => $contact->name,
                'relationship' => $contact->relationship,
                'mobile_phone' => $this->maskPhone($contact->mobile_phone),
                'email' => $this->maskEmail($contact->email),
                'has_contact_details' => (bool) ($contact->mobile_phone || $contact->email),
            ])->values(),
            'gate_status' => $latestGateLog ? [
                'type' => $latestGateLog->type,
                'label' => $latestGateLog->type === 'in' ? 'Last recorded entering campus' : 'Last recorded leaving campus',
                'scan_time' => $latestGateLog->scan_time?->toIso8601String(),
                'gate_location' => $latestGateLog->gate_location,
            ] : null,
            ...$schedule,
        ];
    }

    public function revealedContacts(int $studentId): ?Collection
    {
        $isCurrentStudent = DB::table('student_enrollments')
            ->where('student_id', $studentId)
            ->where('school_year_id', SchoolYear::where('is_current', true)->value('id'))
            ->where('status', 'enrolled')
            ->exists();

        if (! $isCurrentStudent) {
            return null;
        }

        return DB::table('student_parent_contact as link')
            ->join('parent_contacts as contact', 'contact.id', '=', 'link.parent_contact_id')
            ->where('link.student_id', $studentId)
            ->orderBy('contact.name')
            ->get(['contact.id', 'contact.name', 'contact.mobile_phone', 'contact.email', 'link.relationship'])
            ->map(fn ($contact) => [
                'id' => (int) $contact->id,
                'name' => $contact->name,
                'relationship' => $contact->relationship,
                'mobile_phone' => $contact->mobile_phone ?: null,
                'email' => $contact->email ?: null,
            ]);
    }

    public function searchEmployees(string $search): Collection
    {
        $needle = trim($search);

        return User::query()
            ->employees()
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '<>', 'inactive'))
            ->where(function ($query) use ($needle) {
                $query->where('employee_no', $needle)
                    ->orWhere('badge_id', $needle)
                    ->orWhere('name', 'like', '%'.$needle.'%');
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'nickname', 'employee_no', 'position', 'office_id', 'division_id', 'profile_picture'])
            ->map(function (User $employee) {
                $office = $employee->office_id ? DB::table('offices')->where('id', $employee->office_id)->value('name') : null;

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'nickname' => $employee->nickname,
                    'employee_number' => $employee->employee_no,
                    'position' => $employee->position,
                    'office' => $office,
                    'photo_url' => $employee->profile_picture
                        ? route('student-attendance.employee.photo', $employee->id)
                        : null,
                ];
            });
    }

    public function employeeDetail(int $employeeId): ?array
    {
        $employee = User::query()
            ->employees()
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '<>', 'inactive'))
            ->find($employeeId, [
                'id', 'name', 'nickname', 'sex', 'employee_no', 'position', 'specialization',
                'office_id', 'division_id', 'profile_picture',
            ]);

        if (! $employee) {
            return null;
        }

        $office = $employee->office_id ? DB::table('offices')->where('id', $employee->office_id)->value('name') : null;
        $division = $employee->division_id ? DB::table('divisions')->where('id', $employee->division_id)->value('division_name') : null;
        $unit = DB::table('employee_unit_assignments as assignment')
            ->join('organizational_units as unit', 'unit.id', '=', 'assignment.organizational_unit_id')
            ->where('assignment.user_id', $employee->id)
            ->where('assignment.is_primary', true)
            ->whereNull('assignment.end_date')
            ->whereNull('assignment.deleted_at')
            ->whereNull('unit.deleted_at')
            ->value('unit.name');

        $schoolYear = SchoolYear::where('is_current', true)->first(['id', 'name']);
        $schedule = $schoolYear
            ? $this->facultySchedule($employee->id, $schoolYear->id)
            : ['current_or_next' => null, 'schedule' => []];

        $today = now()->toDateString();
        $workSchedule = EmployeeSchedule::where('user_id', $employee->id)
            ->where('status', 'approved')
            ->where('is_default', true)
            ->activeOnDate($today)
            ->latest('effective_date')
            ->first();

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'nickname' => $employee->nickname,
            'sex' => $employee->sex,
            'employee_number' => $employee->employee_no,
            'position' => $employee->position,
            'specialization' => $employee->specialization,
            'office' => $office,
            'division' => $division,
            'unit' => $unit,
            'photo_url' => $employee->profile_picture
                ? route('student-attendance.employee.photo', $employee->id)
                : null,
            'official_hours_today' => $workSchedule && $workSchedule->isWorkDay($today) ? [
                'time_in' => $workSchedule->getTimeIn($today),
                'time_out' => $workSchedule->getTimeOut($today),
            ] : null,
            'school_year' => $schoolYear?->name,
            ...$schedule,
        ];
    }

    private function sectionSchedule(int $sectionId, int $schoolYearId): array
    {
        return $this->schedulePayload(
            ClassSchedule::with(['subject:id,code,name', 'faculty:id,name', 'section:id,sectionname,levelid', 'classroom:id,name,code'])
                ->classes()
                ->active()
                ->where('section_id', $sectionId)
                ->where('school_year_id', $schoolYearId),
            $schoolYearId,
        );
    }

    private function facultySchedule(int $employeeId, int $schoolYearId): array
    {
        return $this->schedulePayload(
            ClassSchedule::with(['subject:id,code,name', 'faculty:id,name', 'section:id,sectionname,levelid', 'classroom:id,name,code'])
                ->classes()
                ->active()
                ->where('user_id', $employeeId)
                ->where('school_year_id', $schoolYearId),
            $schoolYearId,
        );
    }

    private function schedulePayload($query, int $schoolYearId): array
    {
        $termId = AcademicTerm::where('school_year_id', $schoolYearId)
            ->where('is_current', true)
            ->value('id');
        if ($termId) {
            $query->where('academic_term_id', $termId);
        }

        $rows = $query
            ->orderByRaw("FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')")
            ->orderBy('start_time')
            ->get()
            ->map(fn (ClassSchedule $slot) => [
                'id' => $slot->id,
                'day' => $slot->day_of_week,
                'start_time' => substr((string) $slot->start_time, 0, 5),
                'end_time' => substr((string) $slot->end_time, 0, 5),
                'subject' => $slot->subject?->name,
                'subject_code' => $slot->subject?->code,
                'teacher' => $slot->faculty?->name,
                'section' => $slot->section?->sectionname,
                'grade_level' => $slot->section?->levelid,
                'room' => $slot->classroom?->name ?? $slot->classroom?->code,
            ]);

        $now = now();
        $day = $now->englishDayOfWeek;
        $time = $now->format('H:i');
        $todayRows = $rows->where('day', $day);
        $current = $todayRows->first(fn ($slot) => $slot['start_time'] <= $time && $time < $slot['end_time']);
        $next = $current ?: $todayRows->first(fn ($slot) => $slot['start_time'] > $time);

        return [
            'current_or_next' => $next ? [
                ...$next,
                'status' => $current ? 'current' : 'next',
            ] : null,
            'schedule' => $rows->groupBy('day')->map->values(),
        ];
    }

    private function studentName(object $student): string
    {
        $middle = $student->middlename ? ' '.$student->middlename : '';

        return trim("{$student->lastname}, {$student->firstname}{$middle}");
    }

    private function maskPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $visible = substr($phone, -4);

        return str_repeat('•', max(0, strlen($phone) - 4)).$visible;
    }

    private function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return $email ? '••••••' : null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return substr($local, 0, 1).'•••@'.$domain;
    }
}
