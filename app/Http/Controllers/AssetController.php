<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AssetController extends Controller
{
    public function index()
    {
        $assets = Asset::with(['building', 'room', 'assignedUser'])->get();

        $buildings = Building::orderBy('name')->select('id', 'name')->get();
        $rooms = Room::select('id', 'name', 'building_id')->get();
        $users = User::select('id', 'name')->get();

        return Inertia::render('Assets/Index', [
            'assets' => $assets,
            'buildings' => $buildings,
            'rooms' => $rooms,
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'property_no' => 'required|string|unique:assets,property_no',
            'asset_name' => 'required|string',
            'description' => 'nullable|string',
            'category' => 'required|string',
            'serial_number' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'cost' => 'nullable|numeric',
            'condition' => 'nullable|string',
            'building_id' => 'nullable|exists:buildings,id',
            'room_id' => 'nullable|exists:rooms,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'warranty_until' => 'nullable|date',
            'status' => 'nullable|string',
            'remarks' => 'nullable|string',
            'photo' => 'nullable|file|image|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('assets', 'public');
            $data['photo'] = $path;
        }

        Asset::create($data);

        return redirect()->route('assets.index')->with('success', 'Asset added.');
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'property_no' => 'required|string|unique:assets,property_no,' . $asset->id,
            'asset_name' => 'required|string',
            'description' => 'nullable|string',
            'category' => 'required|string',
            'serial_number' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'cost' => 'nullable|numeric',
            'condition' => 'nullable|string',
            'building_id' => 'nullable|exists:buildings,id',
            'room_id' => 'nullable|exists:rooms,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'warranty_until' => 'nullable|date',
            'status' => 'nullable|string',
            'remarks' => 'nullable|string',
            'photo' => 'nullable|file|image|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            if (!empty($asset->photo)) {
                Storage::disk('public')->delete($asset->photo);
            }
            $path = $request->file('photo')->store('assets', 'public');
            $data['photo'] = $path;
        }

        $asset->update($data);

        return redirect()->route('assets.index')->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset)
    {
        if (!empty($asset->photo)) {
            Storage::disk('public')->delete($asset->photo);
        }
        $asset->delete();
        return redirect()->route('assets.index')->with('success', 'Asset deleted.');
    }
}
