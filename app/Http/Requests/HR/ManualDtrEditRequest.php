<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class ManualDtrEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.dtr.manage');
    }

    public function rules(): array
    {
        return [
            'time_in_am'  => 'nullable|date_format:H:i:s',
            'time_out_am' => 'nullable|date_format:H:i:s',
            'time_in_pm'  => 'nullable|date_format:H:i:s',
            'time_out_pm' => 'nullable|date_format:H:i:s',
            'remarks'     => 'nullable|string|max:500',
        ];
    }
}
