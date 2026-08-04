<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grants hr.leave.view + hr.leave.approve to the AUH role as a DATA
 * MIGRATION (prod deploys auto-migrate but never auto-seed) — needed
 * for the new Academic Unit Head leave-recommendation stage (CID teaching
 * faculty's leave is recommended by their AUH before the Division Chief).
 * Deliberately does NOT create the AUH role if it's missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $auhRoleId = DB::table('roles')->where('name', 'AUH')->value('id');
        if (! $auhRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['hr.leave.view', 'hr.leave.approve'])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id'       => $auhRoleId,
            ]);
        }
    }

    public function down(): void
    {
        $auhRoleId = DB::table('roles')->where('name', 'AUH')->value('id');
        if (! $auhRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['hr.leave.view', 'hr.leave.approve'])
            ->pluck('id');

        DB::table('permission_role')
            ->where('role_id', $auhRoleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
