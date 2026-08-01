<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRecordEntryRequest extends FormRequest
{
    public const EMPLOYMENT_STATUSES = [
        'permanent', 'temporary', 'coterminous', 'fixed_term', 'contractual',
        'substitute', 'provisional', 'casual', 'job_order', 'contract_of_service',
    ];

    public const APPOINTMENT_NATURES = [
        'original', 'promotion', 'transfer', 'reemployment', 'reappointment',
        'reinstatement', 'reclassification', 'demotion',
    ];

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('hr.service-records.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'credited_as_government_service' => $this->boolean('credited_as_government_service'),
            'service_to' => $this->input('service_to') ?: null,
            'appointment_issued_at' => $this->input('appointment_issued_at') ?: null,
            'assumed_at' => $this->input('assumed_at') ?: null,
            'csc_action_at' => $this->input('csc_action_at') ?: null,
            'separation_date' => $this->input('separation_date') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'service_from' => ['required', 'date'],
            'service_to' => ['nullable', 'date', 'after_or_equal:service_from'],
            'appointment_issued_at' => ['nullable', 'date'],
            'assumed_at' => ['nullable', 'date'],
            'position_title' => ['required', 'string', 'max:255'],
            'employment_status' => ['nullable', Rule::in(self::EMPLOYMENT_STATUSES)],
            'appointment_nature' => ['nullable', Rule::in(self::APPOINTMENT_NATURES)],
            'agency_name' => ['required', 'string', 'max:255'],
            'station_place' => ['nullable', 'string', 'max:255'],
            'branch_division' => ['nullable', 'string', 'max:255'],
            'plantilla_item_no' => ['nullable', 'string', 'max:80'],
            'salary_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'salary_basis' => ['nullable', Rule::in(['annual', 'monthly', 'daily'])],
            'salary_grade' => ['nullable', 'integer', 'between:1,33'],
            'salary_step' => ['nullable', 'integer', 'between:1,8'],
            'csc_action' => ['nullable', Rule::in(['approved', 'validated', 'attested', 'disapproved', 'invalidated', 'not_applicable', 'pending'])],
            'csc_action_at' => ['nullable', 'date'],
            'credited_as_government_service' => ['required', 'boolean'],
            'separation_date' => ['nullable', 'date', 'after_or_equal:service_from'],
            'separation_cause' => ['nullable', 'string', 'max:255', 'required_with:separation_date'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'lwop_periods' => ['nullable', 'array', 'max:30'],
            'lwop_periods.*.date_from' => ['required', 'date', 'after_or_equal:service_from'],
            'lwop_periods.*.date_to' => ['required', 'date', 'after_or_equal:lwop_periods.*.date_from'],
            'lwop_periods.*.days' => ['nullable', 'numeric', 'min:0', 'max:9999.999'],
            'lwop_periods.*.reference_no' => ['nullable', 'string', 'max:255'],
            'lwop_periods.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $status = $this->input('employment_status');
            if (in_array($status, ['job_order', 'contract_of_service'], true)
                && $this->boolean('credited_as_government_service')) {
                $validator->errors()->add(
                    'credited_as_government_service',
                    'Job Order and Contract of Service engagements cannot be credited as government service.',
                );
            }

            $serviceTo = $this->date('service_to');
            foreach ($this->input('lwop_periods', []) as $index => $period) {
                if ($serviceTo && ! empty($period['date_to']) && $period['date_to'] > $serviceTo->toDateString()) {
                    $validator->errors()->add("lwop_periods.{$index}.date_to", 'LWOP must fall within the service period.');
                }
            }
        });
    }
}
