<?php

namespace App\Http\Requests\HR\OrgUnit;

use App\Models\OrganizationalUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('org.units.create');
    }

    public function rules(): array
    {
        return [
            'code'             => ['required', 'string', 'max:50', 'unique:organizational_units,code'],
            'name'             => ['required', 'string', 'max:255'],
            'short_name'       => ['nullable', 'string', 'max:100'],
            'description'      => ['nullable', 'string'],
            'type'             => ['required', Rule::in(OrganizationalUnit::TYPES)],
            'parent_id'        => ['nullable', 'integer', 'exists:organizational_units,id'],
            'order_index'      => ['nullable', 'integer', 'min:0'],
            'division_id'      => ['nullable', 'integer', 'exists:divisions,id'],
            'office_id'        => ['nullable', 'integer', 'exists:offices,id'],
            'is_active'        => ['boolean'],
            'established_date' => ['nullable', 'date'],
            'abolished_date'   => ['nullable', 'date', 'after_or_equal:established_date'],
            'legal_basis'      => ['nullable', 'string', 'max:255'],
            'mandate'          => ['nullable', 'string'],
            'remarks'          => ['nullable', 'string'],
        ];
    }
}
