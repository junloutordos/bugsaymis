<?php

namespace App\Http\Controllers;

use App\Models\AgencyOutcome;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgencyOutcomeController extends Controller
{
    public function index()
    {
        $outcomes = AgencyOutcome::latest()->get();

        return Inertia::render('PerformanceManagement/AgencyOrgOutcome', [
            'outcomes' => $outcomes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'outcome' => 'required|string|max:255',
            'sub_outcome' => 'nullable|string|max:255',
        ]);

        $outcome = AgencyOutcome::create($data);

        return redirect()->back()->with('outcome', $outcome);
    }

    public function update(Request $request, AgencyOutcome $agencyOutcome)
    {
        $data = $request->validate([
            'outcome' => 'required|string|max:255',
            'sub_outcome' => 'nullable|string|max:255',
        ]);

        $agencyOutcome->update($data);

        return redirect()->back()->with('outcome', $agencyOutcome);
    }

    public function destroy(AgencyOutcome $agencyOutcome)
    {
        $agencyOutcome->delete();

        return redirect()->back();
    }
}
