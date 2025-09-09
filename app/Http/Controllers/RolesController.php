<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
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

        $role = Role::create($data);

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
}
