<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('recruitment.manage');
    }

    public function rules(): array
    {
        return [
            'recruitment_type_id'     => ['required', 'integer', 'exists:recruitment_types,id'],
            'position_title'          => ['required', 'string', 'max:255'],
            'plantilla_item_no'       => ['nullable', 'string', 'max:50'],
            'salary_grade'            => ['nullable', 'integer', 'min:1', 'max:33'],
            'salary_step'             => ['nullable', 'integer', 'min:1', 'max:8'],
            'monthly_salary'          => ['nullable', 'numeric', 'min:0'],
            'daily_rate'              => ['nullable', 'numeric', 'min:0'],
            'qualification_standards' => ['nullable', 'string', 'max:2000'],
            'duties_responsibilities' => ['nullable', 'string', 'max:2000'],
            'education_level'         => ['nullable', 'string', 'max:255'],
            'subject_area'            => ['nullable', 'string', 'max:255'],
            'education'               => ['nullable', 'string', 'max:2000'],
            'experience'              => ['nullable', 'string', 'max:2000'],
            'training'                => ['nullable', 'string', 'max:2000'],
            'eligibility'             => ['nullable', 'string', 'max:2000'],
            'competencies'            => ['nullable', 'array'],
            'competencies.*.name'     => ['required_with:competencies', 'string'],
            'competencies.*.level'    => ['nullable', 'string', 'in:basic,intermediate,advanced'],
            'competencies.*.research' => ['nullable', 'boolean'],
            'duration_type'           => ['nullable', 'string', 'max:50'],
            'budget_source'           => ['nullable', 'string', 'max:100'],
            'submit_to_name'          => ['nullable', 'string', 'max:255'],
            'submit_to_title'         => ['nullable', 'string', 'max:255'],
            'contract_start_date'     => ['nullable', 'date'],
            'contract_end_date'       => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'office_id'               => ['nullable', 'integer', 'exists:offices,id'],
            // Application requirements — array of requirement IDs
            'requirement_ids'         => ['nullable', 'array'],
            'requirement_ids.*'       => ['integer', 'exists:application_requirements,id'],
            // Mandatory flags per requirement
            'requirement_mandatory'   => ['nullable', 'array'],
            'requirement_mandatory.*' => ['boolean'],
        ];
    }
}
