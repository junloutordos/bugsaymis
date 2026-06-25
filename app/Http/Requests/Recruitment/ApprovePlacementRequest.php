<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovePlacementRequest extends FormRequest
{
    // Matches PLANTILLA_NAMES in resources/js/Pages/Recruitment/JobItems/Index.vue
    private const PLANTILLA_TYPE_NAMES = ['Plantilla Teaching', 'Plantilla Non-Teaching'];

    public function authorize(): bool
    {
        return $this->user()->hasPermission('recruitment.approve');
    }

    public function rules(): array
    {
        $application = $this->route('application');
        $jobItemId   = $application?->jobVacancy?->jobItem?->id;
        $isPlantilla = $this->isPlantillaJobItem($application);

        return [
            'assigned_office_id'  => ['nullable', 'integer', 'exists:offices,id'],
            'start_date'          => ['required', 'date'],
            'end_date'            => ['nullable', 'date', 'after_or_equal:start_date'],
            'remarks'             => ['nullable', 'string', 'max:1000'],
            'plantilla_number_id' => [
                $isPlantilla ? 'required' : 'nullable',
                'integer',
                Rule::exists('job_item_plantilla_numbers', 'id')
                    ->where('job_item_id', $jobItemId)
                    ->where('status', 'vacant'),
            ],
        ];
    }

    private function isPlantillaJobItem(?Application $application): bool
    {
        $typeName = $application?->jobVacancy?->jobItem?->recruitmentType?->name;

        return in_array($typeName, self::PLANTILLA_TYPE_NAMES, true);
    }
}
