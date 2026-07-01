<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Roles that can view the Atlas WatchTower dashboard. */
    private array $viewRoles = ['Administrator', 'MIS'];

    /** Roles that can manage WatchTower settings (sampling, thresholds). */
    private array $manageRoles = ['Administrator', 'MIS'];

    public function up(): void
    {
        $view   = $this->upsertPermission('atlas.watchtower.view',   'Atlas', 'View the Atlas WatchTower telemetry dashboard (requests, slow queries, jobs, exceptions)');
        $manage = $this->upsertPermission('atlas.watchtower.manage', 'Atlas', 'Manage Atlas WatchTower settings (sampling, thresholds, ignored routes)');

        foreach ($this->viewRoles as $roleName) {
            $this->assignToRole($view, $roleName);
        }

        foreach ($this->manageRoles as $roleName) {
            $this->assignToRole($manage, $roleName);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', ['atlas.watchtower.view', 'atlas.watchtower.manage'])
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
