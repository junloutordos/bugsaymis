<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * IPCR V2 permissions — mirrors v1's ipcr.* permission/role shape exactly
 * (see database/seeders/RolePermissionSeeder.php).
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\IpcrV2PermissionSeeder --force
 */
class IpcrV2PermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'ipcr.v2.view' => 'View own IPCR V2',
        'ipcr.v2.create' => 'Create IPCR V2 entries',
        'ipcr.v2.update' => 'Update IPCR V2 entries',
        'ipcr.v2.submit' => 'Submit IPCR V2 for approval',
        'ipcr.v2.approve' => 'Approve IPCR V2 submissions',
        'ipcr.v2.monitor' => 'Monitor unit/division IPCR V2',
        'ipcr.v2.admin' => 'Manage IPCR V2 fiscal years and rating periods',
    ];

    private const EMPLOYEE_ROLES = ['Faculty', 'Staff'];
    private const SUPERVISOR_ROLES = ['DivisionChief', 'PMT'];
    private const MONITOR_ROLES = ['HR', 'OCD'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module' => 'IPCR V2',
                'description' => $description,
            ]);
        }

        $this->grant(self::EMPLOYEE_ROLES, ['ipcr.v2.view', 'ipcr.v2.create', 'ipcr.v2.update', 'ipcr.v2.submit']);
        $this->grant(self::SUPERVISOR_ROLES, ['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor']);
        $this->grant(self::MONITOR_ROLES, ['ipcr.v2.view', 'ipcr.v2.monitor', 'ipcr.v2.admin']);
    }

    private function grant(array $roleNames, array $permNames): void
    {
        $ids = Permission::whereIn('name', $permNames)->pluck('id')->all();
        if (empty($ids)) {
            return;
        }
        foreach ($roleNames as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }
}
