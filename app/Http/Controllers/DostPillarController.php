<?php

namespace App\Http\Controllers;

use App\Models\DostPillar;
use Illuminate\Http\Request;

class DostPillarController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'outcome_statement' => 'nullable|string',
            'agency_outcome_ids' => 'array',
            'agency_outcome_ids.*' => 'exists:agency_org_outcomes,id',
        ]);

        $pillar = DostPillar::create(collect($data)->except('agency_outcome_ids')->all());
        $pillar->agencyOutcomes()->sync($data['agency_outcome_ids'] ?? []);

        return back()->with('success', 'Pillar created.');
    }

    public function update(Request $request, DostPillar $dostPillar)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'outcome_statement' => 'nullable|string',
            'agency_outcome_ids' => 'array',
            'agency_outcome_ids.*' => 'exists:agency_org_outcomes,id',
        ]);

        $dostPillar->update(collect($data)->except('agency_outcome_ids')->all());
        $dostPillar->agencyOutcomes()->sync($data['agency_outcome_ids'] ?? []);

        return back()->with('success', 'Pillar updated.');
    }

    public function destroy(DostPillar $dostPillar)
    {
        $dostPillar->delete();

        return back()->with('success', 'Pillar deleted.');
    }
}
