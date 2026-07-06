<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Atlas Sentinel remote-help permission as a DATA MIGRATION (not a
 * seeder) — prod deploys auto-migrate but never auto-seed, so a new permission
 * string must ride in on a migration to actually reach production.
 */
return new class extends Migration
{
    /** Roles that manage the remote-help queue (claim, reveal credentials, end sessions). */
    private array $manageRoles = ['Administrator', 'MIS'];

    public function up(): void
    {
        $manage = $this->upsertPermission(
            'atlas.sentinel.remote-help.manage',
            'Atlas',
            'View and manage the Atlas Sentinel remote-help queue (claim requests, reveal one-time credentials, end sessions)'
        );

        foreach ($this->manageRoles as $roleName) {
            $this->assignToRole($manage, $roleName);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', ['atlas.sentinel.remote-help.manage'])
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

    private function assignToRole(int $permissionId, string $roleName): void
    {
        $role = DB::table('roles')->where('name', $roleName)->first();
        if (! $role) {
            return; // Role doesn't exist in this environment — skip silently
        }

        DB::table('permission_role')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id'       => $role->id,
        ]);
    }
};
