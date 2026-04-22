<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeProfileController extends Controller
{
    public function show(User $user)
    {
        $this->authorize('hr.employee.view');

        $profile = $user->employeeProfile()->firstOrNew(['user_id' => $user->id]);

        // Load primary unit assignment for the sidebar/header display
        $user->load([
            'employeeProfile.salaryGrade',
            'primaryUnitAssignment.unit:id,name,code,type',
        ]);

        return Inertia::render('HR/Employees/Profile', [
            'employee'       => $user,
            'profile'        => $profile,
            'primaryUnit'    => $user->primaryUnitAssignment?->unit,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('hr.employee.manage');

        $data = $request->validate([
            'employee_no'                 => 'nullable|string|max:30',
            'salary_grade_id'             => 'nullable|exists:salary_grades,id',
            'step'                        => 'nullable|integer|min:1|max:8',
            'employment_type'             => 'nullable|in:permanent,casual,contractual,coterminous,substitute',
            'appointment_status'          => 'nullable|string|max:50',
            'item_no'                     => 'nullable|string|max:50',
            'division'                    => 'nullable|string|max:100',
            'section'                     => 'nullable|string|max:100',
            'immediate_supervisor_id'     => 'nullable|exists:users,id',
            'division_chief_id'           => 'nullable|exists:users,id',
            'date_original_appointment'   => 'nullable|date',
            'date_last_promotion'         => 'nullable|date',
            'date_of_birth'               => 'nullable|date',
            'civil_status'                => 'nullable|in:single,married,widowed,divorced,separated',
            'tin'                         => 'nullable|string|max:20',
            'sss_no'                      => 'nullable|string|max:20',
            'gsis_no'                     => 'nullable|string|max:30',
            'pagibig_no'                  => 'nullable|string|max:20',
            'philhealth_no'               => 'nullable|string|max:20',
            'bank_name'                   => 'nullable|string|max:100',
            'bank_account_no'             => 'nullable|string|max:50',
            'blood_type'                  => 'nullable|string|max:5',
            'emergency_contact_name'      => 'nullable|string|max:150',
            'emergency_contact_phone'     => 'nullable|string|max:20',
        ]);

        $user->employeeProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        return back()->with('success', 'Employee profile updated.');
    }
}
