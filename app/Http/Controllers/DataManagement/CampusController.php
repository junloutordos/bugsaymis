<?php

namespace App\Http\Controllers\DataManagement;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CampusController extends Controller
{
    public function index()
    {
        $campus = Campus::first(); // Get the single campus
        return Inertia::render('DataManagement/Campus/Index', [
            'campus' => $campus,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'year_established' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'address' => 'nullable|string',
            'telephone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'facebook' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate logo upload
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('campuses', 'public');
            $data['logo'] = $logoPath;
        }

        Campus::create($data);

        return redirect()->route('campuses.index')->with('success', 'Campus created.');
    }

    public function update(Request $request, Campus $campus)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'year_established' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'address' => 'nullable|string',
            'telephone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'facebook' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate logo upload
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($campus->logo && Storage::disk('public')->exists($campus->logo)) {
                Storage::disk('public')->delete($campus->logo);
            }
            $logoPath = $request->file('logo')->store('campuses', 'public');
            $data['logo'] = $logoPath;
        }

        $campus->update($data);

        return redirect()->route('campuses.index')->with('success', 'Campus updated.');
    }

    public function destroy(Campus $campus)
    {
        $campus->delete();
        return redirect()->route('campuses.index')->with('success', 'Campus deleted.');
    }
}
