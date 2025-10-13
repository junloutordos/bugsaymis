<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Division;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'division.divisionchief'])
            ->select('id', 'name', 'email', 'role_id', 'position', 'division_id', 'office', 'created_at')
            ->get();

        // For dropdowns
        $roles = Role::select('id', 'name')->get();
        $divisions = Division::where('status', 'active') // 👈 only active divisions
        ->select('id', 'division_name')     
        ->get();

        return Inertia::render('Users/Index', [
            'users'     => $users,
            'roles'     => $roles,
            'divisions' => $divisions,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'role_id'     => 'required|exists:roles,id',
            'position'    => 'nullable|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'office'      => 'nullable|string|max:255',
        ]);

        User::create($data);

        return redirect()->route('users.index')->with('success', 'User created successfully');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email,' . $user->id,
            'role_id'     => 'required|exists:roles,id',
            'position'    => 'nullable|string|max:255',
            'division_id' => 'nullable|exists:divisions,id',
            'office'      => 'nullable|string|max:255',
        ]);

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
