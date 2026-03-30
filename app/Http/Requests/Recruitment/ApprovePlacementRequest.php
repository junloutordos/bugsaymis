<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('recruitment.approve');
    }

    public function rules(): array
    {
        return [
            'assigned_office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['nullable', 'date', 'after_or_equal:start_date'],
            'remarks'            => ['nullable', 'string', 'max:1000'],
        ];
    }
}
