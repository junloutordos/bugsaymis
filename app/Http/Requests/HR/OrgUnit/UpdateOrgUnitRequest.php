<?php

namespace App\Http\Requests\HR\OrgUnit;

use App\Models\OrganizationalUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('org.units.update');
    }

    public function rules(): array
    {
        $unitId = $this->route('unit')?->id ?? $this->route('unit');

        return [
            'code'             => ['sometimes', 'required', 'string', 'max:50',
                                   Rule::unique('organizational_units', 'code')->ignore($unitId)],
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'short_name'       => ['nullable', 'string', 'max:100'],
            'description'      => ['nullable', 'string'],
            'type'             => ['sometimes', 'required', Rule::in(OrganizationalUnit::TYPES)],
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

    /**
     * Additional validation: guard against circular parent assignment.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $unit = $this->route('unit');
            $proposedParentId = $this->input('parent_id');

            if ($unit && $proposedParentId && $proposedParentId != $unit->parent_id) {
                try {
                    $unit->guardCircularReference((int) $proposedParentId);
                } catch (\RuntimeException $e) {
                    $validator->errors()->add('parent_id', $e->getMessage());
                }
            }
        });
    }
}
