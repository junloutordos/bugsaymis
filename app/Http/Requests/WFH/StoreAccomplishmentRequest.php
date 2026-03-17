<?php

namespace App\Http\Requests\WFH;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccomplishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],

            // proof_type drives which proof field is required
            'proof_type'  => ['nullable', Rule::in(['photo', 'link'])],

            // Required when proof_type = 'link'
            'proof_link'  => [
                Rule::requiredIf(fn () => $this->proof_type === 'link'),
                'nullable',
                'url',
                'max:2048',
            ],

            // Required when proof_type = 'photo'
            'photo'       => [
                Rule::requiredIf(fn () => $this->proof_type === 'photo'),
                'nullable',
                'file',
                'mimes:jpeg,jpg,png,webp',
                'max:10240', // 10 MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'      => 'Accomplishment title is required.',
            'proof_link.required' => 'A URL is required when proof type is set to link.',
            'proof_link.url'      => 'Proof link must be a valid URL.',
            'photo.required'      => 'A photo file is required when proof type is set to photo.',
            'photo.mimes'         => 'Proof photo must be a JPEG, PNG, or WebP image.',
            'photo.max'           => 'Proof photo must not exceed 10 MB.',
        ];
    }
}
