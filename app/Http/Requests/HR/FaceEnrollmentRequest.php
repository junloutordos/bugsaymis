<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class FaceEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate handled via route permission middleware
    }

    public function rules(): array
    {
        return [
            // Base64-encoded JPEG captured from the device camera
            'photo'   => ['required', 'string'],
            'consent' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required'   => 'A camera capture is required to enroll your face.',
            'consent.accepted' => 'You must consent to facial data collection before enrolling.',
        ];
    }
}
