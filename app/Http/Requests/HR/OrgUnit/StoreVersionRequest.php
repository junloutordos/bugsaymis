<?php

namespace App\Http\Requests\HR\OrgUnit;

use Illuminate\Foundation\Http\FormRequest;

class StoreVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('org.versions.manage');
    }

    public function rules(): array
    {
        return [
            'version_number'  => ['required', 'string', 'max:50', 'unique:organizational_versions,version_number'],
            'name'            => ['required', 'string', 'max:255'],
            'effective_date'  => ['required', 'date'],
            'end_date'        => ['nullable', 'date', 'after:effective_date'],
            'change_summary'  => ['nullable', 'string'],
            'basis_document'  => ['nullable', 'string', 'max:255'],
        ];
    }
}
