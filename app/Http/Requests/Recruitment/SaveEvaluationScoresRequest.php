<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class SaveEvaluationScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('recruitment.evaluate');
    }

    public function rules(): array
    {
        return [
            'scores'                => ['required', 'array', 'min:1'],
            'scores.*.criteria_id'  => ['required', 'integer', 'exists:evaluation_criteria,id'],
            'scores.*.score'        => ['required', 'numeric', 'min:0', 'max:100'],
            'scores.*.remarks'      => ['nullable', 'string', 'max:500'],
        ];
    }
}
