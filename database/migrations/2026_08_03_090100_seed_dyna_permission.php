<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'atlas.dyna.access'],
            [
                'module' => 'Atlas',
                'description' => 'Use the Dyna AI assistant (analytics & insights chat)',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $roleIds = DB::table('roles')->whereIn('name', ['Administrator', 'OCD', 'DivisionChief'])->pluck('id');
        $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
