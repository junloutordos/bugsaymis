<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'Security Guard')->value('id');

        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'Security Guard',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionId = DB::table('permissions')->where('name', 'students.attendance.scan')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module' => 'Student Attendance',
                'name' => 'students.attendance.scan',
                'description' => 'Operate the gate kiosk scanner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permission_role')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'Security Guard')->value('id');

        if ($roleId) {
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            DB::table('role_user')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
