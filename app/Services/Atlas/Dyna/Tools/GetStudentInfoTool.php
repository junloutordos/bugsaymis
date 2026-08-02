<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Discipline\DisciplineCase;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;

class GetStudentInfoTool implements DynaTool
{
    public function name(): string { return 'get_student_info'; }

    public function description(): string
    {
        return 'Returns one student\'s profile: enrollment status, grade/section, and attendance summary. '
             . 'Discipline history is included only if the requester also has discipline access. Requires student enrollment view access.';
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

        $student = Student::where('lastname', 'like', '%'.$input['identifier'].'%')
            ->orWhere('firstname', 'like', '%'.$input['identifier'].'%')
            ->orWhere('pisaysystemID', $input['identifier'])
            ->first();

        if (! $student) {
            return ['note' => "No student found matching \"{$input['identifier']}\"."];
        }

        $enrollment = StudentEnrollment::where('student_id', $student->id)->latest('id')->first();

        $result = [
            'name' => $student->full_name,
            'enrollment_status' => $enrollment?->status,
            'grade_level' => $enrollment?->grade_level,
        ];

        if ($user->hasPermission('discipline.view')) {
            $result['discipline_cases'] = DisciplineCase::where('student_id', $student->id)
                ->get(['case_no', 'status', 'threat_level', 'nature_of_offense'])
                ->toArray();
        }

        return $result;
    }
}
