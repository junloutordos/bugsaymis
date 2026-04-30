<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Roles that can create and manage their own activities. */
    private array $manageRoles = [
        'Administrator',
        'MIS', 'Payroll Officer', 'HRMPSB', 'Recruitment Officer',
        'DivisionChief', 'OCD', 'PMT', 'CID Chief', 'Faculty', 'Staff',
        'Registrar', 'Records', 'Librarian', 'Nurse', 'Guidance',
        'GSU Head', 'FAD Chief', 'InformationOfficer', 'Dorm Manager', 'HR',
    ];

    /** Roles that can view ALL activities (read-only). */
    private array $viewAllRoles = ['HR'];

    public function up(): void
    {
        // Insert permissions — skip if already present
        $manage  = $this->upsertPermission('activities.manage',   'AMS', 'Create and manage own activities, participants, and certificates');
        $viewAll = $this->upsertPermission('activities.view_all', 'AMS', 'View all activities read-only (HR Office)');

        // Assign activities.manage to all qualifying roles
        foreach ($this->manageRoles as $roleName) {
            $this->assignToRole($manage, $roleName);
        }

        // Assign activities.view_all to HR
        foreach ($this->viewAllRoles as $roleName) {
            $this->assignToRole($viewAll, $roleName);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', ['activities.manage', 'activities.view_all'])
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

        // Ignore duplicate — pivot has a composite primary key
        DB::table('permission_role')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id'       => $role->id,
        ]);
    }
};
