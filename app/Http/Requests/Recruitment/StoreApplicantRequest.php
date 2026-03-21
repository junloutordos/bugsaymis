<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('recruitment.manage');
    }

    public function rules(): array
    {
        $applicantId = $this->route('applicant')?->id;

        return [
            'first_name'     => ['required', 'string', 'max:100'],
            'middle_name'    => ['nullable', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'suffix'         => ['nullable', 'string', 'max:10'],
            'birthdate'      => ['nullable', 'date', 'before:today'],
            'address'        => ['nullable', 'string', 'max:500'],
            'civil_status'   => ['nullable', 'in:single,married,widowed,separated'],
            'email'          => [
                'required', 'email', 'max:255',
                'unique:applicants,email,' . ($applicantId ?? 'NULL'),
            ],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'eligibility'    => ['nullable', 'string', 'max:255'],
            'prc_license_no' => ['nullable', 'string', 'max:50'],
            'school'         => ['nullable', 'string', 'max:255'],
            'course'         => ['nullable', 'string', 'max:255'],
            'year_graduated' => ['nullable', 'integer', 'min:1970', 'max:' . date('Y')],
            'source'         => ['nullable', 'string', 'max:100'],
        ];
    }
}
