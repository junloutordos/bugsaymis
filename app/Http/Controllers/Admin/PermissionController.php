<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * GET /admin/rbac/permissions
     * All permissions, grouped by module.
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('module')->orderBy('name')
            ->withCount('roles')
            ->get(['id', 'name', 'module', 'description']);

        $grouped = $permissions->groupBy('module')->map(fn ($items, $module) => [
            'module'      => $module,
            'permissions' => $items->values(),
        ])->values();

        return response()->json($grouped);
    }

    /**
     * POST /admin/rbac/permissions
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:permissions,name|regex:/^[a-z0-9._-]+$/',
            'module'      => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
        ]);

        $permission = Permission::create($data);

        return response()->json($permission, 201);
    }

    /**
     * PUT /admin/rbac/permissions/{permission}
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:permissions,name,' . $permission->id . '|regex:/^[a-z0-9._-]+$/',
            'module'      => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
        ]);

        $permission->update($data);

        return response()->json($permission);
    }

    /**
     * DELETE /admin/rbac/permissions/{permission}
     * Also detaches from all roles automatically (cascade handled by FK or manual detach).
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $permission->roles()->detach();
        $permission->delete();

        return response()->json(['message' => 'Permission deleted.']);
    }
}
