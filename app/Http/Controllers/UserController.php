<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Division;
use App\Models\Office;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'division.divisionchief', 'office'])
            ->select('id', 'name', 'sex','email', 'role_id', 'position', 'division_id', 'office_id', 'created_at')
            ->get();

        // For dropdowns
        $roles = Role::select('id', 'name')->get();
        $divisions = Division::where('status', 'active') // 👈 only active divisions
            ->select('id', 'division_name')
            ->get();

        // Offices for dependent dropdown
        $offices = Office::select('id', 'name', 'division_id')->get();

        return Inertia::render('Users/Index', [
            'users'     => $users,
            'roles'     => $roles,
            'divisions' => $divisions,
            'offices'   => $offices,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'sex'         => 'nullable|string|max:10',
            'email'       => 'required|email|unique:users,email',
            'position'    => 'nullable|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'office_id'   => 'nullable|exists:offices,id',
        ]);

        // Normalize role_id input: accept array or comma-separated string
        $roleInput = $request->input('role_id');
        $roleIds = [];
        if (is_array($roleInput)) {
            $roleIds = array_map('intval', $roleInput);
        } elseif (is_string($roleInput)) {
            $roleIds = array_filter(array_map('trim', explode(',', $roleInput)), fn($v) => $v !== '');
            $roleIds = array_map('intval', $roleIds);
        } elseif ($roleInput !== null) {
            $roleIds = [intval($roleInput)];
        }

        if (empty($roleIds)) {
            return back()->withErrors(['role_id' => 'Please select at least one role.']);
        }

        // ensure all provided role ids exist
        $count = Role::whereIn('id', $roleIds)->count();
        if ($count !== count($roleIds)) {
            return back()->withErrors(['role_id' => 'One or more selected roles are invalid.']);
        }

        $data['role_id'] = implode(',', $roleIds);

        User::create($data);

        return redirect()->route('users.index')->with('success', 'User created successfully');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'sex'         => 'nullable|string|max:10',
            'email'       => 'required|email|unique:users,email,' . $user->id,
            'position'    => 'nullable|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'office_id'   => 'nullable|exists:offices,id',
        ]);

        // Normalize role_id input
        $roleInput = $request->input('role_id');
        $roleIds = [];
        if (is_array($roleInput)) {
            $roleIds = array_map('intval', $roleInput);
        } elseif (is_string($roleInput)) {
            $roleIds = array_filter(array_map('trim', explode(',', $roleInput)), fn($v) => $v !== '');
            $roleIds = array_map('intval', $roleIds);
        } elseif ($roleInput !== null) {
            $roleIds = [intval($roleInput)];
        }

        if (empty($roleIds)) {
            return back()->withErrors(['role_id' => 'Please select at least one role.']);
        }

        // ensure all provided role ids exist
        $count = Role::whereIn('id', $roleIds)->count();
        if ($count !== count($roleIds)) {
            return back()->withErrors(['role_id' => 'One or more selected roles are invalid.']);
        }

        $data['role_id'] = implode(',', $roleIds);

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated successfully');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }
}
