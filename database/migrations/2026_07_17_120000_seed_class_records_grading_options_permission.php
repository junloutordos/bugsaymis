<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the scoped "class-records.grading-options" permission as a DATA
 * MIGRATION (prod deploys auto-migrate but never auto-seed) and grants it to
 * the AUH role. Lets Academic Unit Heads manage the shared grading options
 * (create/edit/delete options + categories) WITHOUT the much broader
 * class-records.admin (see-all-records, assign teacher, check, quarter
 * lock/unlock). GradingOptionController accepts either permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionId = $this->upsertPermission(
            'class-records.grading-options',
            'ClassRecord',
            'Manage grading options and their categories (scoped subset of class-records.admin)'
        );

        // Grant to the existing AUH role only — deliberately does NOT create
        // the role if it's missing (that would be a different decision).
        $auhRoleId = DB::table('roles')->where('name', 'AUH')->value('id');
        if ($auhRoleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id'       => $auhRoleId,
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->where('name', 'class-records.grading-options')
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    private function upsertPermission(string $name, string $module, string $description): int
    {
        $existing = DB::table('permissions')->where('name', $name)->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table('permissions')->insertGetId([
            'name'        => $name,
            'module'      => $module,
            'description' => $description,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
};
