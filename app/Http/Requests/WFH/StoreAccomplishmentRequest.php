<?php

namespace App\Http\Requests\WFH;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAccomplishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_id' => ['nullable', 'integer', 'exists:wfh_attendances,id'],
            'title'         => ['nullable', 'string', 'max:255'],
            'description'   => ['required', 'string', 'max:2000'],
            'time_from'     => ['nullable', 'date_format:H:i'],
            'time_to'       => ['nullable', 'date_format:H:i'],

            // proof_type drives which proof field is required
            'proof_type'  => ['nullable', Rule::in(['photo', 'link'])],

            // Required when proof_type = 'link'
            'proof_link'  => [
                Rule::requiredIf(fn () => $this->proof_type === 'link'),
                'nullable',
                'url',
                'max:2048',
            ],

            // Required when proof_type = 'photo' — sent as base64 data URI
            'photo_base64' => [
                Rule::requiredIf(fn () => $this->proof_type === 'photo'),
                'nullable',
                'string',
            ],
            'photo_name'   => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $from = $this->input('time_from');
            $to   = $this->input('time_to');
            if ($from && $to && strtotime($to) <= strtotime($from)) {
                $v->errors()->add('time_to', 'Time To must be after Time From.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Accomplishment details are required.',
            'proof_link.required' => 'A URL is required when proof type is set to link.',
            'proof_link.url'      => 'Proof link must be a valid URL.',
            'photo_base64.required' => 'A photo is required when proof type is set to photo.',
        ];
    }
}
