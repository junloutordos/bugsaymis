<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Administrator']);
    }

    public function index()
    {
        // Eager-load division and unit head user to avoid N+1 queries.
        $offices = Office::with(['division', 'unitHeadUser'])
            ->select('id', 'name', 'division_id', 'unit_head')
            ->orderBy('name')
            ->get();
        $divisions = \App\Models\Division::where('status', 'active')->orderBy('division_name')->get();
        $users = User::select('id', 'name')->orderBy('name')->get();
        return inertia('DataManagement/Offices/Index', compact('offices', 'divisions', 'users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191|unique:offices,name',
            'description' => 'nullable|string',
            'division_id' => 'required|exists:divisions,id',
            'unit_head' => 'nullable|exists:users,id',
        ]);

        $office = Office::create($data);

        return redirect()->back()->with('success', 'Office created.');
    }

    public function update(Request $request, Office $office)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191|unique:offices,name,' . $office->id,
            'description' => 'nullable|string',
            'division_id' => 'required|exists:divisions,id',
            'unit_head' => 'nullable|exists:users,id',
        ]);

        $office->update($data);

        return redirect()->back()->with('success', 'Office updated.');
    }

    public function destroy(Office $office)
    {
        $office->delete();
        return redirect()->back()->with('success', 'Office deleted.');
    }
}
