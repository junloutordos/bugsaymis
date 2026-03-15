<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function index()
    {
        $activities = Activity::orderBy('date')->get();
        return Inertia::render('Activities/Index', [
            'activities' => $activities,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'venue' => 'nullable|string|max:255',
            'participants' => 'nullable|string',
            'materials_no_cost' => 'nullable|string',
            'materials_with_cost' => 'nullable|string',
            'working_committee' => 'nullable|string',
        ]);

        $activity = Activity::create($data);

        if ($request->wantsJson()) {
            return response()->json($activity, 201);
        }

        return redirect()->route('activities.index')->with('success', 'Activity created');
    }

    public function update(Request $request, Activity $activity)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'venue' => 'nullable|string|max:255',
            'participants' => 'nullable|string',
            'materials_no_cost' => 'nullable|string',
            'materials_with_cost' => 'nullable|string',
            'working_committee' => 'nullable|string',
        ]);

        $activity->update($data);

        if ($request->wantsJson()) {
            return response()->json($activity, 200);
        }

        return redirect()->route('activities.index')->with('success', 'Activity updated');
    }

    public function destroy(Activity $activity)
    {
        $activity->delete();
        return redirect()->route('activities.index')->with('success', 'Activity deleted');
    }
}
