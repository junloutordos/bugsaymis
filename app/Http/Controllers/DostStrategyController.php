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
            'agency_outcome_id' => 'nullable|exists:agency_org_outcomes,id',
            'name' => 'required|string|max:255',
        ]);

        DostStrategy::create($data);

        return back()->with('success', 'Strategy created.');
    }

    public function update(Request $request, DostStrategy $dostStrategy)
    {
        $data = $request->validate([
            'dost_pillar_id' => 'required|exists:dost_pillars,id',
            'agency_outcome_id' => 'nullable|exists:agency_org_outcomes,id',
            'name' => 'required|string|max:255',
        ]);

        $dostStrategy->update($data);

        return back()->with('success', 'Strategy updated.');
    }

    public function destroy(DostStrategy $dostStrategy)
    {
        $dostStrategy->delete();

        return back()->with('success', 'Strategy deleted.');
    }
}
