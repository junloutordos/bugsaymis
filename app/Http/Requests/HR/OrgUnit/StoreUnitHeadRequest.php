<?php

namespace App\Http\Requests\HR\OrgUnit;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitHeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('org.heads.manage');
    }

    public function rules(): array
    {
        return [
            'organizational_unit_id'  => ['required', 'integer', 'exists:organizational_units,id'],
            'user_id'                 => ['required', 'integer', 'exists:users,id'],
            'designation_title'       => ['nullable', 'string', 'max:255'],
            'is_acting'               => ['boolean'],
            'is_current'              => ['boolean'],
            'effective_date'          => ['required', 'date'],
            'end_date'                => ['nullable', 'date', 'after:effective_date'],
            'designation_order'       => ['nullable', 'string', 'max:100'],
            'designation_order_date'  => ['nullable', 'date'],
            'remarks'                 => ['nullable', 'string'],
        ];
    }
}
