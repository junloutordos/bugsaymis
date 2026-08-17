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
        ]);

        DostPillar::create($data);

        return back()->with('success', 'Pillar created.');
    }

    public function update(Request $request, DostPillar $dostPillar)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'outcome_statement' => 'nullable|string',
        ]);

        $dostPillar->update($data);

        return back()->with('success', 'Pillar updated.');
    }

    public function destroy(DostPillar $dostPillar)
    {
        $dostPillar->delete();

        return back()->with('success', 'Pillar deleted.');
    }
}
