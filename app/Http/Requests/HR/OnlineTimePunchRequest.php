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
            'punch_type'               => ['required', 'string', 'in:time_in_am,time_out_am,time_in_pm,time_out_pm'],
            'challenge_token'          => ['required', 'string'],
            'frames'                   => ['required', 'array', 'min:4', 'max:7'],
            'frames.*.data'            => ['required', 'string'], // base64 data URI
            'frames.*.capturedAtMs'    => ['required', 'integer', 'min:0'],
            'latitude'                 => ['required', 'numeric', 'between:-90,90'],
            'longitude'                => ['required', 'numeric', 'between:-180,180'],
            'accuracy'                 => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
