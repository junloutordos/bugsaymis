<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\StudentSectionResolver;

class GetClassScheduleTool implements DynaTool
{
    public function name(): string { return 'get_class_schedule'; }

    public function description(): string
    {
        return 'Returns class schedule entries (subject, section, day, time) for a faculty member '
             . '(by name/email) or a student\'s current section, for the active school year and term. '
             . 'Use for "what\'s X teaching", "what\'s X\'s schedule", or "what classes does section Y have".';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'faculty_identifier' => ['type' => 'string', 'description' => 'Faculty member name or email — returns their teaching schedule.'],
                'student_identifier' => ['type' => 'string', 'description' => 'Student name or system ID — returns their current section\'s schedule.'],
                'day' => ['type' => 'string', 'description' => 'Optional: filter to one day (Monday, Tuesday, Wednesday, Thursday, Friday, or Saturday).'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->hasPermission('faculty_loading.view') && ! $user->hasPermission('faculty_loading.view_own')) {
            throw new \RuntimeException('This account does not have faculty loading access.');
        }

        $currentSchoolYearId = SchoolYear::where('is_current', true)->value('id');

        $query = ClassSchedule::with(['faculty', 'subject', 'section'])
            ->active()
            ->when($currentSchoolYearId, fn ($q) => $q->where('school_year_id', $currentSchoolYearId));

        if (! empty($input['faculty_identifier'])) {
            $faculty = User::where('name', 'like', '%'.$input['faculty_identifier'].'%')
                ->orWhere('email', $input['faculty_identifier'])
                ->first();

            if (! $faculty) {
                return ['note' => "No faculty member found matching \"{$input['faculty_identifier']}\"."];
            }

            $isSelf = $user->id === $faculty->id;
            if (! $isSelf && ! $user->hasPermission('faculty_loading.view')) {
                throw new \RuntimeException('This account can only view its own schedule.');
            }

            $query->forFaculty($faculty->id);
        } elseif (! empty($input['student_identifier'])) {
            // A student's schedule is never "your own" for a Dyna user (Dyna users are
            // staff/faculty, not students) — view_own only covers self-lookups in the
            // faculty branch above, so this always needs the broader view permission.
            if (! $user->hasPermission('faculty_loading.view')) {
                throw new \RuntimeException('This account can only view its own schedule.');
            }

            $student = \DB::table('students')
                ->where('lastname', 'like', '%'.$input['student_identifier'].'%')
                ->orWhere('firstname', 'like', '%'.$input['student_identifier'].'%')
                ->orWhere('pisaysystemID', $input['student_identifier'])
                ->first();

            if (! $student) {
                return ['note' => "No student found matching \"{$input['student_identifier']}\"."];
            }

            $section = (new StudentSectionResolver())->latestForStudent($student->id);
            if (! $section || ! $section->sectionid) {
                return ['note' => 'No current section found for this student.'];
            }

            $query->where('section_id', $section->sectionid);
        } else {
            return ['note' => 'Provide either a faculty_identifier or a student_identifier.'];
        }

        if (! empty($input['day'])) {
            $query->where('day_of_week', $input['day']);
        }

        $schedule = $query->orderBy('day_of_week')->orderBy('start_time')->get()->map(fn (ClassSchedule $s) => [
            'day' => $s->day_of_week,
            'start_time' => $s->start_time,
            'end_time' => $s->end_time,
            'subject' => $s->subject?->name,
            'faculty' => $s->faculty?->name,
            'section' => $s->section?->name ?? $s->section_id,
        ])->values()->toArray();

        return ['schedule' => $schedule];
    }
}
