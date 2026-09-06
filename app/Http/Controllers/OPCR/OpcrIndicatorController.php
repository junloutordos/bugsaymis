<?php

namespace App\Http\Controllers\OPCR;

use App\Http\Controllers\Controller;
use App\Models\OPCR\OpcrIndicator;
use Illuminate\Http\Request;

class OpcrIndicatorController extends Controller
{
    private function rules(): array
    {
        return [
            'fiscal_year' => 'required|integer|min:2000|max:2100',
            'agency_outcome_id' => 'required|exists:agency_org_outcomes,id',
            'performance_indicator_id' => 'nullable|exists:performance_indicators,id',
            'description' => 'required|string',
            'target' => 'nullable|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'division_ids' => 'array',
            'division_ids.*' => 'exists:divisions,id',
        ];
    }

    // Fields mirrored from a linked Performance Indicator by
    // OpcrIndicatorPropagationService — never editable from the OPCR side
    // once propagated, so a manual edit here can't be silently clobbered the
    // next time the source Performance Indicator is saved.
    private const SYNCED_FIELDS = ['fiscal_year', 'agency_outcome_id', 'description', 'target', 'budget', 'performance_indicator_id'];

    public function update(Request $request, OpcrIndicator $opcrIndicator)
    {
        $data = $request->validate($this->rules());

        if ($opcrIndicator->performance_indicator_id) {
            foreach (self::SYNCED_FIELDS as $field) {
                $data[$field] = $opcrIndicator->{$field};
            }
            $data['division_ids'] = $opcrIndicator->divisions->pluck('id')->all();
        }

        $opcrIndicator->update($data);
        $opcrIndicator->divisions()->sync($data['division_ids'] ?? []);

        return back()->with('success', 'Indicator updated.');
    }

    public function updateActual(Request $request, OpcrIndicator $opcrIndicator)
    {
        $data = $request->validate([
            'quarter' => 'required|integer|between:1,4',
            'value' => 'nullable|string|max:255',
        ]);

        $opcrIndicator->actuals()->updateOrCreate(
            ['quarter' => $data['quarter']],
            ['value' => $data['value'] ?? null]
        );

        return back()->with('success', "Q{$data['quarter']} actual recorded.");
    }

    public function updateAccomplishment(Request $request, OpcrIndicator $opcrIndicator)
    {
        $data = $request->validate([
            'accomplishment' => 'nullable|string',
        ]);

        $opcrIndicator->update(['accomplishment' => $data['accomplishment'] !== '' ? ($data['accomplishment'] ?? null) : null]);

        return back()->with('success', 'Accomplishment updated.');
    }

    public function updateRating(Request $request, OpcrIndicator $opcrIndicator)
    {
        $data = $request->validate([
            'rating_quality' => 'nullable|numeric|between:1,5',
            'rating_efficiency' => 'nullable|numeric|between:1,5',
            'rating_timeliness' => 'nullable|numeric|between:1,5',
            'rating_average' => 'nullable|numeric|between:1,5',
        ]);

        $opcrIndicator->update($data);

        return back()->with('success', 'Rating recorded.');
    }
}
