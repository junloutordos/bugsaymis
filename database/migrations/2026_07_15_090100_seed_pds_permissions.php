<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        ['module' => 'PDS', 'name' => 'pds.view_all', 'description' => 'View all employee Personal Data Sheets (HR Office)'],
    ];

    private array $rolePermissions = [
        'HR' => [
            'pds.view_all',
        ],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            $existing = DB::table('permissions')->where('name', $permission['name'])->first();

            if ($existing) {
                DB::table('permissions')
                    ->where('id', $existing->id)
                    ->update([
                        'module'      => $permission['module'],
                        'description' => $permission['description'],
                        'updated_at'  => now(),
                    ]);
                continue;
            }

            DB::table('permissions')->insert([
                'name'        => $permission['name'],
                'module'      => $permission['module'],
                'description' => $permission['description'],
                'updated_at'  => now(),
                'created_at'  => now(),
            ]);
        }

        foreach ($this->rolePermissions as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            if (! $roleId) {
                continue;
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('name', $permissionNames)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id'       => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $names = array_column($this->permissions, 'name');
        $permissionIds = DB::table('permissions')->whereIn('name', $names)->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
