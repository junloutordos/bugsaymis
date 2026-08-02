<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'CID Chief')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'CID Chief')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
        }
    }
};
