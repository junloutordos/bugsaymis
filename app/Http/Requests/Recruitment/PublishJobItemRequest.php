<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class PublishJobItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('recruitment.publish');
    }

    public function rules(): array
    {
        return [
            'posting_date'     => ['required', 'date'],
            'closing_date'     => ['required', 'date', 'after:posting_date'],
            'publication_type' => ['required', 'in:internal,external,both'],
        ];
    }
}
