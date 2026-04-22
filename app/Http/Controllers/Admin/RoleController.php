<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * GET /admin/rbac/roles
     * List all roles with permission count and user count.
     */
    public function index(): JsonResponse
    {
        $roles = Role::withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json($roles);
    }

    /**
     * GET /admin/rbac/roles/{role}
     * Single role with its full permission list.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions:id,name,module,description');

        return response()->json($role);
    }

    /**
     * POST /admin/rbac/roles
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:roles,name',
            'description' => 'nullable|string|max:255',
        ]);

        $role = Role::create($data);

        return response()->json($role, 201);
    }

    /**
     * PUT /admin/rbac/roles/{role}
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:255',
        ]);

        $role->update($data);

        return response()->json($role);
    }

    /**
     * DELETE /admin/rbac/roles/{role}
     * Prevent deleting the Administrator role.
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'Administrator') {
            return response()->json(['message' => 'Cannot delete the Administrator role.'], 422);
        }

        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    /**
     * PUT /admin/rbac/roles/{role}/permissions
     * Replace the role's entire permission set.
     * Body: { "permission_ids": [1, 3, 7, ...] }
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permission_ids'   => 'required|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role->permissions()->sync($data['permission_ids']);

        // Return fresh permission list
        $role->load('permissions:id,name,module,description');

        return response()->json([
            'message'     => 'Permissions updated.',
            'permissions' => $role->permissions,
        ]);
    }

    /**
     * GET /admin/rbac/permissions
     * All permissions grouped by module — useful for the role-edit UI.
     */
    public function allPermissions(): JsonResponse
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get(['id', 'name', 'module', 'description']);

        $grouped = $permissions->groupBy('module')->map(fn ($items, $module) => [
            'module'      => $module,
            'permissions' => $items->values(),
        ])->values();

        return response()->json($grouped);
    }
}
