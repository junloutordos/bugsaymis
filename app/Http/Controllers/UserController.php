<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Division;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'division.divisionchief', 'office'])
            ->select('id', 'name','sex', 'email', 'badge_id', 'role_id', 'position', 'division_id', 'office_id', 'profile_picture', 'electronic_signature', 'created_at')
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

    /**
     * Return a lightweight JSON list of users for select/dropdowns.
     * Accessible to authenticated users.
     */
    public function selectList(Request $request)
    {
        $query = User::query()->select('id', 'name', 'badge_id', 'office_id');

        // optional filter by emp_category or search
        if ($request->filled('emp_category')) {
            $query->where('emp_category', $request->input('emp_category'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('badge_id', 'like', "%{$q}%");
            });
        }

        $users = $query->orderBy('name')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'badge_id' => $u->badge_id,
                'office_id' => $u->office_id,
            ];
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sex'         => 'nullable|in:Male,Female',
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'emp_category' => 'nullable|in:Plantilla Teaching,Plantilla Non-Teaching,COS Teaching,COS Non Teaching',
            // badge_id stores biometric ID; allow nullable for existing users
            // require alpha-numeric, dash or underscore only for formatting
            'badge_id'    => ['nullable','string','max:64','regex:/^[A-Za-z0-9_\\-]+$/','unique:users,badge_id'],
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
            'sex'         => 'nullable|in:Male,Female',
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email,' . $user->id,
            'emp_category' => 'nullable|in:Plantilla Teaching,Plantilla Non-Teaching,COS Teaching,COS Non Teaching',
            // allow keeping or changing badge_id; unique except for this user
            'badge_id'    => ['nullable','string','max:64','regex:/^[A-Za-z0-9_\\-]+$/','unique:users,badge_id,' . $user->id],
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

    public function uploadSignature(Request $request, User $user)
    {
        $data = $request->validate([
            'electronic_signature' => 'required|file|mimes:png|dimensions:max_width=1200,max_height=600|max:2048',
        ]);

        if ($request->hasFile('electronic_signature')) {
            // remove old signature if exists
            if (!empty($user->electronic_signature)) {
                Storage::disk('public')->delete($user->electronic_signature);
            }

            $path = $request->file('electronic_signature')->store('signatures', 'public');
            $user->electronic_signature = $path;
            $user->save();
        }

        return redirect()->route('users.index')->with('success', 'Electronic signature uploaded.');
    }
}
