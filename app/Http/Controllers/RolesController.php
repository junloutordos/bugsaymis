<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class RolesController extends Controller
{
    /**
     * List all roles
     */
    public function index()
    {
        $roles = Role::select('id', 'name', 'created_at')->get();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show a specific role
     */
    public function show($id)
    {
        $role = Role::findOrFail($id);

        return Inertia::render('Roles/Show', [
            'role' => $role,
        ]);
    }

    /**
     * Store a new role
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
        ]);

        Role::create($data);

        return redirect()->route('roles.index')->with('success', 'Role created successfully');
    }

    /**
     * Update a role
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
        ]);

        $role->update($data);

        return redirect()->route('roles.index')->with('success', 'Role updated successfully');
    }

    /**
     * Delete a role
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return back()->with('success', 'Role deleted successfully.');
    }

    // -------------------------------
    // DIVISIONS SECTION
    // -------------------------------

    /**
     * List all divisions and all users
     */
    public function showDivisions()
    {
        $divisions = Division::with('divisionchief') // load chief user
            ->select('id', 'division_name', 'acronym', 'division_chief_id', 'year', 'status', 'signature_path', 'created_at')
            ->get();

        $users = User::select('id', 'name', 'email')->get();

        return Inertia::render('Roles/Division', [
            'divisions' => $divisions,
            'users' => $users, // pass all users to frontend
        ]);
    }

    /**
     * Upload an electronic signature for a division
     */
    public function uploadSignature(Request $request, Division $division)
    {
        $request->validate([
            'signature_base64' => ['required', 'string', 'starts_with:data:image/'],
        ]);

        $base64   = preg_replace('/^data:image\/\w+;base64,/', '', $request->signature_base64);
        $data     = base64_decode($base64);
        $s3Key    = 'signatures/divisions/division_' . $division->id . '_' . time() . '.png';

        if ($division->signature_path && Storage::disk('s3')->exists($division->signature_path)) {
            Storage::disk('s3')->delete($division->signature_path);
        }

        Storage::disk('s3')->put($s3Key, $data, ['ContentType' => 'image/png']);

        $division->signature_path = $s3Key;
        $division->save();

        return back()->with('success', 'Signature uploaded successfully');
    }

    /**
     * Show a specific division with its chief + all users
     */
    public function showDivision($id)
    {
        $division = Division::with('divisionchief')->findOrFail($id);
        $users = User::select('id', 'name', 'email')->get();

        return Inertia::render('Divisions/Show', [
            'division' => $division,
            'users' => $users,
        ]);
    }

    /**
     * Store a new division
     */
    public function storeDivision(Request $request)
    {
        $data = $request->validate([
            'division_name' => 'required|string|max:255|unique:divisions,division_name',
            'acronym' => 'nullable|string|max:20',
            'division_chief_id' => 'nullable|exists:users,id',
            'year' => 'nullable|digits:4|integer',
            'status' => 'in:active,not_active',
        ]);

        Division::create($data);

        return redirect()->route('roles.divisions')->with('success', 'Division created successfully');
    }

    /**
     * Update a division
     */
    public function updateDivision(Request $request, $id)
    {
        $division = Division::findOrFail($id);

        $data = $request->validate([
            'division_name' => 'required|string|max:255|unique:divisions,division_name,' . $division->id,
            'acronym' => 'nullable|string|max:20',
            'division_chief_id' => 'nullable|exists:users,id',
            'year' => 'nullable|digits:4|integer',
            'status' => 'in:active,not_active',
        ]);

        $division->update($data);

        return redirect()->route('roles.divisions')->with('success', 'Division updated successfully');
    }

    /**
     * Delete a division
     */
    public function destroyDivision($id)
    {
        $division = Division::findOrFail($id);
        $division->delete();

        return back()->with('success', 'Division deleted successfully.');
    }
}
