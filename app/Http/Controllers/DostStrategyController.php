<?php

namespace App\Http\Controllers;

use App\Models\DostStrategy;
use Illuminate\Http\Request;

class DostStrategyController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'dost_pillar_id' => 'required|exists:dost_pillars,id',
            'name' => 'required|string|max:255',
            'agency_outcome_ids' => 'array',
            'agency_outcome_ids.*' => 'exists:agency_org_outcomes,id',
        ]);

        $strategy = DostStrategy::create(collect($data)->except('agency_outcome_ids')->all());
        $strategy->agencyOutcomes()->sync($data['agency_outcome_ids'] ?? []);

        return back()->with('success', 'Strategy created.');
    }

    public function update(Request $request, DostStrategy $dostStrategy)
    {
        $data = $request->validate([
            'dost_pillar_id' => 'required|exists:dost_pillars,id',
            'name' => 'required|string|max:255',
            'agency_outcome_ids' => 'array',
            'agency_outcome_ids.*' => 'exists:agency_org_outcomes,id',
        ]);

        $dostStrategy->update(collect($data)->except('agency_outcome_ids')->all());
        $dostStrategy->agencyOutcomes()->sync($data['agency_outcome_ids'] ?? []);

        return back()->with('success', 'Strategy updated.');
    }

    public function destroy(DostStrategy $dostStrategy)
    {
        $dostStrategy->delete();

        return back()->with('success', 'Strategy deleted.');
    }
}
