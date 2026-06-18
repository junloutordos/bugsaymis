<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class OnlineTimePunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate handled via route permission middleware
    }

    public function rules(): array
    {
        return [
            'punch_type'  => ['required', 'string', 'in:time_in_am,time_out_am,time_in_pm,time_out_pm'],
            'session_id'  => ['required', 'string'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
