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
            'dost_sub_strategy_id' => 'nullable|exists:dost_sub_strategies,id',
            'agency_outcome_id' => 'nullable|exists:agency_org_outcomes,id',
            'performance_indicator_id' => 'nullable|exists:performance_indicators,id',
            'description' => 'required|string',
            'target' => 'nullable|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'division_ids' => 'array',
            'division_ids.*' => 'exists:divisions,id',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge($this->rules(), [
            'opcr_period_id' => 'required|exists:opcr_periods,id',
        ]));

        $indicator = OpcrIndicator::create($data);
        $indicator->divisions()->sync($data['division_ids'] ?? []);

        return back()->with('success', 'Indicator created.');
    }

    public function update(Request $request, OpcrIndicator $opcrIndicator)
    {
        $data = $request->validate($this->rules());

        $opcrIndicator->update($data);
        $opcrIndicator->divisions()->sync($data['division_ids'] ?? []);

        return back()->with('success', 'Indicator updated.');
    }

    public function destroy(OpcrIndicator $opcrIndicator)
    {
        $opcrIndicator->delete();

        return back()->with('success', 'Indicator deleted.');
    }
}
