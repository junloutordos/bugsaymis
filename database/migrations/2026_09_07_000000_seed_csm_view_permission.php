<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a `csm.view` permission as a DATA MIGRATION (not a seeder) — prod
 * deploys auto-migrate but never auto-seed, so a new permission/role
 * assignment must ride in on a migration to actually reach production.
 *
 * CSMFeedbackController previously hardcoded access to
 * hasAnyRole(['Administrator', 'MIS']) with no permission check at all.
 * This introduces a proper permission string, assigns it to MIS (preserves
 * current access) and Evaluation Committee (new access being granted), and
 * the controller/routes are updated in the same deploy to check it.
 * Administrator needs no explicit grant — isSuperAdmin() bypasses all checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionId = $this->upsertPermission(
            'csm.view',
            'CSM',
            'View CSM Feedback dashboard, response list, and export reports'
        );

        foreach (['MIS', 'Evaluation Committee'] as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if ($roleId) {
                $this->assignPermissionToRoleId($permissionId, $roleId);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', ['csm.view'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

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

    private function assignPermissionToRoleId(int $permissionId, int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id'       => $roleId,
        ]);
    }
};
