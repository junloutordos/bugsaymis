<?php

namespace App\Http\Requests\WFH;

use Illuminate\Foundation\Http\FormRequest;

class TimeOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Base64-encoded JPEG captured from the device camera
            'photo'     => ['required', 'string'],   // base64 data URI

            // Optional geolocation
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'A camera capture is required to time out.',
        ];
    }
}
