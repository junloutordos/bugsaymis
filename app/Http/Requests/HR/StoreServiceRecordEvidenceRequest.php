<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRecordEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('hr.service-records.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in([
                'appointment', 'assumption_to_duty', 'nosa_nosi', 'prior_service_record',
                'office_order', 'separation', 'lwop_certification', 'other',
            ])],
            'title' => ['required', 'string', 'max:255'],
            'filename' => ['required', 'string', 'max:255'],
            'data_uri' => ['required', 'string', 'max:15000000'],
        ];
    }
}
