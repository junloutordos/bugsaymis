<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class FacilityController extends Controller
{
    /**
     * Display a listing of facilities (admin view).
     */
    public function index(Request $request)
    {
        $facilities = Facility::orderBy('name')->get();

        return Inertia::render('Facilities/Index', [
            'facilities' => $facilities,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        Facility::create($request->only(['name','location','capacity','description','status']));

        return redirect()->route('facilities.index')->with('success', 'Facility added');
    }

    public function update(Request $request, Facility $facility): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:30',
        ]);

        $facility->update($request->only(['name','location','capacity','description','status']));

        return redirect()->route('facilities.index')->with('success', 'Facility updated');
    }

    public function destroy(Facility $facility): Response
    {
        $facility->delete();
        return response('Deleted', 200);
    }
}
