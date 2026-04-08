<?php

namespace App\Http\Requests\HR\OrgUnit;

use App\Models\HR\EmployeeUnitAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('org.assign');
    }

    public function rules(): array
    {
        return [
            'user_id'                 => ['required', 'integer', 'exists:users,id'],
            'organizational_unit_id'  => ['required', 'integer', 'exists:organizational_units,id'],
            'is_primary'              => ['boolean'],
            'assignment_type'         => ['required', Rule::in([
                EmployeeUnitAssignment::ASSIGNMENT_ORGANIC,
                EmployeeUnitAssignment::ASSIGNMENT_SECONDED,
                EmployeeUnitAssignment::ASSIGNMENT_DESIGNATED,
                EmployeeUnitAssignment::ASSIGNMENT_CONCURRENT,
                EmployeeUnitAssignment::ASSIGNMENT_DETAILED,
            ])],
            'appointment_type'        => ['nullable', Rule::in([
                EmployeeUnitAssignment::APPOINTMENT_PERMANENT,
                EmployeeUnitAssignment::APPOINTMENT_TEMPORARY,
                EmployeeUnitAssignment::APPOINTMENT_CASUAL,
                EmployeeUnitAssignment::APPOINTMENT_COS,
                EmployeeUnitAssignment::APPOINTMENT_JOB_ORDER,
                EmployeeUnitAssignment::APPOINTMENT_SECONDMENT,
            ])],
            'position_title'          => ['nullable', 'string', 'max:255'],
            'item_number'             => ['nullable', 'string', 'max:50'],
            'effective_date'          => ['required', 'date'],
            'end_date'                => ['nullable', 'date', 'after:effective_date'],
            'order_number'            => ['nullable', 'string', 'max:100'],
            'remarks'                 => ['nullable', 'string'],
        ];
    }
}
