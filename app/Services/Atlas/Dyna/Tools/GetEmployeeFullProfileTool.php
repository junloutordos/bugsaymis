<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Applicant;
use App\Models\Document;
use App\Models\EmployeeIPCR;
use App\Models\FacilityRequest;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\HR\DtrRecord;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveCredit;
use App\Models\ITJobRequest;
use App\Models\Payroll\PayrollRecord;
use App\Models\Pds;
use App\Models\SALN\SalnRecord;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VehicleRequest;
use App\Models\WFHAttendance;

class GetEmployeeFullProfileTool implements DynaTool
{
    public function name(): string { return 'get_employee_full_profile'; }

    public function description(): string
    {
        return 'Returns a comprehensive profile for one employee: leave credits/history, DTR summary, '
             . 'PDS (including personal/contact details, address, government ID numbers, work experience, '
             . 'civil service eligibility, voluntary work, and family background), SALN filing status, IPCR '
             . 'history, faculty loading (if applicable), payroll summary, committee memberships and '
             . 'accomplishment ratings, gate pass history, recent IT/facility/vehicle/service request '
             . 'filings, documents currently routed to or filed by this employee, recruitment history, and '
             . 'WFH summary — each section only included if the requesting user has access to it. Use for '
             . 'open-ended "tell me about employee X" questions (including birthdate, contact number, '
             . 'address, or other personal details); use get_employee_info instead for a quick single-fact '
             . 'lookup.';
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

            $personalInfo = $pds->personalInfo;
            if ($personalInfo) {
                $profile['personal_info'] = [
                    'date_of_birth' => $personalInfo->date_of_birth,
                    'civil_status' => $personalInfo->civil_status,
                    'blood_type' => $personalInfo->blood_type,
                    'mobile_no' => $personalInfo->mobile_no,
                    'telephone_no' => $personalInfo->telephone_no,
                    'email_address' => $personalInfo->email_address,
                    'tin_no' => $personalInfo->tin_no,
                    'philhealth_no' => $personalInfo->philhealth_no,
                    'pagibig_id_no' => $personalInfo->pagibig_id_no,
                    'umid_id_no' => $personalInfo->umid_id_no,
                    'residential_address' => implode(', ', array_filter([
                        $personalInfo->residential_house, $personalInfo->residential_street,
                        $personalInfo->residential_barangay, $personalInfo->residential_city,
                        $personalInfo->residential_province,
                    ])),
                    'permanent_address' => implode(', ', array_filter([
                        $personalInfo->permanent_house, $personalInfo->permanent_street,
                        $personalInfo->permanent_barangay, $personalInfo->permanent_city,
                        $personalInfo->permanent_province,
                    ])),
                ];
            }

            $profile['work_experience'] = $pds->workExperience()->get(['position', 'agency', 'from_date', 'to_date', 'appointment_status'])->toArray();
            $profile['eligibility'] = $pds->eligibility()->get(['eligibility', 'rating', 'exam_date', 'license_number'])->toArray();
            $profile['voluntary_work'] = $pds->voluntaryWork()->get(['organization', 'from_date', 'to_date', 'hours', 'nature_of_work'])->toArray();

            $familyBackground = $pds->familyBackground;
            if ($familyBackground) {
                $profile['family_background'] = $familyBackground->only([
                    'spouse_surname', 'spouse_first_name', 'spouse_middle_name', 'spouse_occupation',
                    'father_surname', 'father_first_name', 'father_middle_name',
                    'mother_maiden_surname', 'mother_maiden_first_name', 'mother_maiden_middle_name',
                ]);
            }
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
            $assignments = FacultyCommitteeAssignment::with('accomplishments')->where('user_id', $employee->id)->where('status', 'active')->get();
            $profile['committees'] = $assignments->map(fn (FacultyCommitteeAssignment $a) => ['committee_name' => $a->committee_name, 'role' => $a->role])->toArray();

            $accomplishments = $assignments->flatMap->accomplishments;
            if ($accomplishments->isNotEmpty()) {
                $profile['committee_accomplishments'] = $accomplishments
                    ->map(fn ($a) => ['accomplishment' => $a->accomplishment, 'sup_quality' => $a->sup_quality, 'sup_efficiency' => $a->sup_efficiency, 'sup_timeliness' => $a->sup_timeliness, 'sup_average' => $a->sup_average])
                    ->values()->toArray();
            }
        }

        if ($user->hasPermission('hr.dtr.view') || $isSelf) {
            if ($employee->badge_id) {
                $profile['gate_pass_recent'] = \DB::table('gatepass')
                    ->whereRaw('CAST(badgeNumber AS CHAR) = ?', [(string) $employee->badge_id])
                    ->orderByDesc('id')->limit(5)
                    ->get(['gatepass_type', 'gatepass_date', 'destination', 'purpose', 'status'])
                    ->map(fn ($row) => (array) $row)->toArray();
            } else {
                $profile['gate_pass_recent'] = [];
            }
        }

        // No dedicated "view all employees' requests/documents" permission exists in the
        // seeded permission set — restrict to self rather than inventing one, matching the
        // isSelf-only fallback used elsewhere in this method when no broader grant applies.
        if ($isSelf) {
            $profile['requests_recent'] = collect()
                ->merge(ITJobRequest::where('user_id', $employee->id)->latest('id')->limit(5)->get()->map(fn (ITJobRequest $r) => ['type' => 'IT Job Request', 'title' => $r->title, 'status' => $r->status]))
                ->merge(FacilityRequest::where('requestor_id', $employee->id)->latest('id')->limit(5)->get()->map(fn (FacilityRequest $r) => ['type' => 'Facility Request', 'title' => $r->activity, 'status' => $r->status]))
                ->merge(VehicleRequest::where('requestor_id', $employee->id)->latest('id')->limit(5)->get()->map(fn (VehicleRequest $r) => ['type' => 'Vehicle Request', 'title' => $r->purpose, 'status' => $r->status]))
                ->merge(ServiceRequest::where('requestor_id', $employee->id)->latest('id')->limit(5)->get()->map(fn (ServiceRequest $r) => ['type' => 'Service Request', 'title' => $r->service_type, 'status' => $r->status]))
                ->values()->toArray();

            $profile['documents_with_employee'] = Document::where('current_holder_id', $employee->id)
                ->orWhere('created_by', $employee->id)
                ->latest('id')->limit(10)
                ->get(['tracking_no', 'subject', 'overall_status', 'created_by', 'current_holder_id'])
                ->map(fn (Document $d) => ['tracking_no' => $d->tracking_no, 'subject' => $d->subject, 'status' => $d->overall_status, 'role' => $d->created_by === $employee->id ? 'filed_by' : 'current_holder'])
                ->toArray();
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
