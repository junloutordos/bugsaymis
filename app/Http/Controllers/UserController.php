<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users
     */
    public function index()
    {
        $users = User::with('role')
            ->select('id', 'name', 'email', 'role_id', 'created_at')
            ->get();

        $roles = Role::select('id', 'name')->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Show user profile
     */
    public function show($id)
    {
        $user = User::with(['role', 'jobRequests'])->findOrFail($id);

        return Inertia::render('Users/Show', [
            'user' => $user
        ]);
    }

    /**
     * Store a new user
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create($data);

        return redirect()->route('users.index')->with('success', 'User created successfully');
    }


    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated successfully');
}



    /**
     * Delete user
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }
}
