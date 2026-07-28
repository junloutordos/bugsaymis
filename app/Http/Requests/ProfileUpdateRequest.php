<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255'],
            'nickname'             => ['nullable', 'string', 'max:50'],
            'prenominal_title'     => ['nullable', 'string', 'max:50'],
            'postnominal_title'    => ['nullable', 'string', 'max:100'],
            'specialization'       => ['nullable', 'string', 'max:255'],
            'profile_photo_base64' => ['nullable', 'string'],
            'profile_photo_mime'   => ['nullable', 'string', 'in:image/jpeg,image/jpg,image/png'],
        ];
    }
}
