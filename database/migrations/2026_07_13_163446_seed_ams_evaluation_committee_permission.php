<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a dedicated "Evaluation Committee" permission + role as a DATA
 * MIGRATION (not a seeder) — prod deploys auto-migrate but never auto-seed,
 * so a new permission/role must ride in on a migration to actually reach
 * production. Distinct from activities.monitor (HR-only) so specific
 * individual users can be assigned this role via /admin/assign-roles
 * without touching their primary role or HR's own access.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionId = $this->upsertPermission(
            'activities.evaluation_committee',
            'AMS',
            'Monitor evaluation results and analytics for activities (assignable to individual evaluation committee members)'
        );

        $roleId = $this->upsertRole(
            'Evaluation Committee',
            'Cross-cutting role for individually-assigned evaluation committee members who monitor activity evaluations.'
        );

        $this->assignPermissionToRoleId($permissionId, $roleId);
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', ['activities.evaluation_committee'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        // Deliberately does NOT delete the "Evaluation Committee" role itself —
        // an admin may have already assigned it to users; leaving an empty,
        // permission-less role behind is harmless and non-destructive.
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

    private function upsertRole(string $name, string $description): int
    {
        $existing = DB::table('roles')->where('name', $name)->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table('roles')->insertGetId([
            'name'        => $name,
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
