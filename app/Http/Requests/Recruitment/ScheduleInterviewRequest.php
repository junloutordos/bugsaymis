<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('recruitment.evaluate');
    }

    public function rules(): array
    {
        return [
            'interview_date'  => ['required', 'date', 'after:now'],
            'panel_members'   => ['required', 'array', 'min:1'],
            'panel_members.*' => ['integer', 'exists:users,id'],
            'venue'           => ['nullable', 'string', 'max:255'],
            'format'          => ['required', 'in:panel,individual,demo-teaching'],
        ];
    }
}
