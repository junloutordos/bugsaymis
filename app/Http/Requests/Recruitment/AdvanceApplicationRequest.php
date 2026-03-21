<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;

class AdvanceApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('recruitment.evaluate');
    }

    public function rules(): array
    {
        return [
            'stage'   => ['required', 'string', 'in:' . implode(',', Application::STAGES)],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
