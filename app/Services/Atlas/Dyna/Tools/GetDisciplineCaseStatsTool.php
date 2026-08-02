<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Discipline\DisciplineCase;
use App\Models\Student;
use App\Models\User;

class GetDisciplineCaseStatsTool implements DynaTool
{
    public function name(): string { return 'get_discipline_case_stats'; }

    public function description(): string
    {
        return 'Returns discipline case data. Without student_identifier: aggregate counts by status and threat level. '
             . 'With student_identifier (name or ID): that specific student\'s case list. Requires discipline access.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_identifier' => [
                    'type' => 'string',
                    'description' => 'Optional — a student name or ID to look up that specific student\'s discipline cases instead of aggregate counts.',
                ],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->hasPermission('discipline.view')) {
            throw new \RuntimeException('This account does not have discipline access.');
        }

        if (! empty($input['student_identifier'])) {
            return $this->individualCases($input['student_identifier']);
        }

        return [
            'byStatus' => DisciplineCase::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->toArray(),
            'byThreatLevel' => DisciplineCase::query()->selectRaw('threat_level, count(*) as total')->groupBy('threat_level')->pluck('total', 'threat_level')->toArray(),
        ];
    }

    private function individualCases(string $identifier): array
    {
        $student = Student::where('lastname', 'like', "%{$identifier}%")
            ->orWhere('firstname', 'like', "%{$identifier}%")
            ->orWhere('pisaysystemID', $identifier)
            ->first();

        if (! $student) {
            return ['cases' => [], 'note' => "No student found matching \"{$identifier}\"."];
        }

        $cases = DisciplineCase::where('student_id', $student->id)->get([
            'case_no', 'status', 'threat_level', 'nature_of_offense', 'incident_date', 'resolution', 'sanction',
        ]);

        return [
            'student' => $student->full_name,
            'cases' => $cases->toArray(),
        ];
    }
}
