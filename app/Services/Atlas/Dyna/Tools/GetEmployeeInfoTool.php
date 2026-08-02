<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\EmployeeIPCR;
use App\Models\HR\DtrRecord;
use App\Models\User;

class GetEmployeeInfoTool implements DynaTool
{
    public function name(): string { return 'get_employee_info'; }

    public function description(): string
    {
        return 'Returns one employee\'s profile: position, division/office, status, salary grade/step, '
             . 'latest DTR attendance status, and current-period IPCR status. Requires HR employee access.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['identifier'],
            'properties' => [
                'identifier' => ['type' => 'string', 'description' => 'Employee name or email.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->hasPermission('hr.employees.manage')) {
            throw new \RuntimeException('This account does not have HR employee access.');
        }

        $query = User::with(['division', 'office'])
            ->where(function ($q) use ($input) {
                $q->where('name', 'like', '%'.$input['identifier'].'%')
                    ->orWhere('email', $input['identifier']);
            });

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->where('division_id', $user->division_id);
        }

        $employee = $query->first();

        if (! $employee) {
            return ['note' => "No employee found matching \"{$input['identifier']}\" in your accessible scope."];
        }

        $latestDtr = DtrRecord::where('user_id', $employee->id)->orderByDesc('work_date')->first();
        $currentIpcr = EmployeeIPCR::where('user_id', $employee->id)->orderByDesc('rating_period_id')->first();

        return [
            'name' => $employee->name,
            'position' => $employee->position,
            'division' => $employee->division?->division_name,
            'status' => $employee->status,
            'salary_grade' => $employee->salary_grade,
            'salary_step' => $employee->salary_step,
            'latest_dtr_status' => $latestDtr?->attendance_status,
            'latest_dtr_date' => $latestDtr?->work_date,
            'current_ipcr_status' => $currentIpcr?->status,
            'current_ipcr_rating' => $currentIpcr?->final_adjectival_rating,
        ];
    }
}
