<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * @deprecated Permissions and role assignments for the Payroll/HR module have
 *             been merged into PermissionsSeeder and RolePermissionSeeder.
 *             This class is kept as a stub to avoid breaking any existing
 *             DatabaseSeeder references.
 */
class PayrollPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('PayrollPermissionsSeeder is deprecated. Run PermissionsSeeder + RolePermissionSeeder instead.');
    }
}
