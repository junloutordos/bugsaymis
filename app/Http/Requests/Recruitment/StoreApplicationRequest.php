<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('recruitment.apply')
            || $this->user()->hasPermission('recruitment.manage');
    }

    public function rules(): array
    {
        return [
            'applicant_id'   => ['required', 'integer', 'exists:applicants,id'],
            'job_vacancy_id' => ['required', 'integer', 'exists:job_vacancies,id'],
            'is_internal'    => ['boolean'],
            'remarks'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}
