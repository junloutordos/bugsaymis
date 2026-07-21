<?php

namespace App\Http\Requests;

use App\Services\StudentProfileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StudentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-students') ?? false;
    }

    public function rules(StudentProfileService $profileService): array
    {
        return $profileService->validationRules(collect(DB::select('SHOW COLUMNS FROM students')));
    }
}
