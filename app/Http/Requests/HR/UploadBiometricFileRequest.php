<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UploadBiometricFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.biometric.manage');
    }

    public function rules(): array
    {
        return [
            'files'     => 'required|array|min:1|max:20',
            'files.*'   => 'required|file|max:20480',   // .dat files are binary; no MIME check
            'device_id' => 'nullable|string|max:50',
        ];
    }
}
