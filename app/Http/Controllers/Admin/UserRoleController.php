<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * GET /admin/rbac/users
     * Paginated user list with their assigned roles.
     * Supports ?search=, ?role=, ?per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles:id,name')
            ->select('id', 'name', 'email', 'position', 'status');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%");
            });
        }

        if ($roleName = $request->query('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $roleName));
        }

        $perPage = min((int) $request->query('per_page', 20), 100);

        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json($users);
    }

    /**
     * GET /admin/rbac/users/{user}
     * Single user with their full role list.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('roles:id,name,description');

        return response()->json([
            'id'       => $user->id,
            'name'     => $user->name,
            'email'    => $user->email,
            'position' => $user->position,
            'status'   => $user->status,
            'roles'    => $user->roles,
        ]);
    }

    /**
     * PUT /admin/rbac/users/{user}/roles
     * Replace the user's entire role set.
     * Body: { "role_ids": [2, 5] }
     *
     * Also syncs the legacy role_id column (first role ID) for backward compatibility,
     * and clears the permission cache so the next request re-loads permissions.
     */
    public function sync(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_ids'   => 'required|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $roleIds = $data['role_ids'];

        // Pivot sync
        $user->roles()->sync($roleIds);

        // Legacy column: store comma-separated IDs so old code still works
        $user->role_id = implode(',', $roleIds);
        $user->save();

        // Bust permission cache for this user instance
        $user->clearPermissionCache();

        $user->load('roles:id,name');

        return response()->json([
            'message' => 'Roles updated.',
            'roles'   => $user->roles,
        ]);
    }

    /**
     * GET /admin/rbac/roles-list
     * Flat list of all roles for dropdown usage.
     */
    public function rolesList(): JsonResponse
    {
        $roles = Role::with(['permissions' => fn ($q) => $q->select('permissions.id', 'permissions.name', 'permissions.module', 'permissions.description')])
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json($roles);
    }
}
