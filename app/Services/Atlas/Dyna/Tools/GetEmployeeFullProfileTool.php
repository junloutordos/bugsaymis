<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Applicant;
use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\HR\DtrRecord;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveCredit;
use App\Models\Payroll\PayrollRecord;
use App\Models\Pds;
use App\Models\SALN\SalnRecord;
use App\Models\User;
use App\Models\WFHAttendance;

class GetEmployeeFullProfileTool implements DynaTool
{
    public function name(): string { return 'get_employee_full_profile'; }

    public function description(): string
    {
        return 'Returns a comprehensive profile for one employee: leave credits/history, DTR summary, '
             . 'PDS, SALN filing status, IPCR history, faculty loading (if applicable), payroll summary, '
             . 'committee memberships, recruitment history, and WFH summary — each section only included '
             . 'if the requesting user has access to it. Use for open-ended "tell me about employee X" '
             . 'questions; use get_employee_info instead for a quick single-fact lookup.';
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

        $query = User::where(function ($q) use ($input) {
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

        $isSelf = $user->id === $employee->id;
        $profile = [
            'name' => $employee->name,
            'position' => $employee->position,
            'status' => $employee->status,
        ];

        if ($user->hasPermission('hr.leave.credits.view') || $isSelf) {
            $profile['leave'] = [
                'credits' => LeaveCredit::with('leaveType')->where('user_id', $employee->id)->where('year', now()->year)->get()
                    ->map(fn (LeaveCredit $c) => ['type' => $c->leaveType?->name, 'balance' => $c->balance])->values()->toArray(),
                'recent_applications' => LeaveApplication::with('leaveType')->where('user_id', $employee->id)
                    ->latest('date_from')->limit(5)->get()
                    ->map(fn (LeaveApplication $a) => ['type' => $a->leaveType?->name, 'from' => $a->date_from, 'to' => $a->date_to, 'status' => $a->status])->values()->toArray(),
            ];
        }

        if ($user->hasPermission('hr.dtr.view') || $isSelf) {
            $profile['dtr_recent'] = DtrRecord::where('user_id', $employee->id)->orderByDesc('work_date')->limit(10)->get()
                ->map(fn (DtrRecord $d) => ['date' => $d->work_date, 'status' => $d->attendance_status, 'hours' => $d->hours_worked])->values()->toArray();
        }

        $pds = Pds::where('user_id', $employee->id)->first();
        if ($pds && $pds->canBeViewedBy($user)) {
            $profile['pds'] = [
                'education' => $pds->education()->get(['level', 'school_name', 'year_graduated'])->toArray(),
                'trainings_count' => $pds->trainings()->count(),
            ];
        }

        if ($user->hasPermission('saln.view_all') || $isSelf) {
            $profile['saln'] = SalnRecord::where('user_id', $employee->id)->orderByDesc('year')->limit(3)->get()
                ->map(fn (SalnRecord $s) => ['year' => $s->year, 'status' => $s->status, 'filed_at' => $s->filed_at])->values()->toArray();
        }

        if ($user->hasPermission('ipcr.view') || $isSelf) {
            $profile['ipcr_history'] = EmployeeIPCR::with('period')->where('user_id', $employee->id)
                ->orderByDesc('id')->limit(5)->get()
                ->map(fn (EmployeeIPCR $i) => ['period' => $i->period?->name, 'status' => $i->status, 'rating' => $i->final_adjectival_rating])->values()->toArray();
        }

        if ($user->hasPermission('faculty_loading.view') || ($isSelf && $user->hasPermission('faculty_loading.view_own'))) {
            $loads = LoadAssignment::where('user_id', $employee->id)->get(['assignment_type', 'subject_id', 'load_units']);
            if ($loads->isNotEmpty()) {
                $profile['faculty_loading'] = $loads->toArray();
            }
        }

        if ($user->hasPermission('payroll.view_all') || ($isSelf && $user->hasPermission('payroll.view_own'))) {
            $profile['payroll_recent'] = PayrollRecord::where('user_id', $employee->id)->orderByDesc('id')->limit(3)->get()
                ->map(fn (PayrollRecord $p) => ['net_pay' => $p->net_pay, 'gross_pay' => $p->gross_pay, 'days_worked' => $p->days_worked])->values()->toArray();
        }

        if ($user->hasPermission('faculty_loading.view') || $isSelf) {
            $profile['committees'] = FacultyCommitteeAssignment::where('user_id', $employee->id)->where('status', 'active')
                ->get(['committee_name', 'role'])->toArray();
        }

        if ($user->hasPermission('recruitment.view')) {
            $applicant = Applicant::where('email', $employee->email)->first();
            if ($applicant) {
                $profile['recruitment'] = $applicant->applications()->with('placement')->get()
                    ->map(fn ($app) => ['stage' => $app->current_stage, 'placement_status' => $app->placement?->status])->values()->toArray();
            }
        }

        if ($user->hasPermission('wfh.view') || $isSelf) {
            $profile['wfh_recent'] = WFHAttendance::where('user_id', $employee->id)->orderByDesc('date')->limit(5)->get()
                ->map(fn (WFHAttendance $w) => ['date' => $w->date, 'time_in' => $w->time_in, 'time_out' => $w->time_out])->values()->toArray();
        }

        return $profile;
    }
}
