<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * @deprecated Merged into PermissionsSeeder + RolePermissionSeeder.
 *             Kept as stub to avoid breaking DatabaseSeeder references.
 */
class PPMPPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('PPMPPermissionSeeder is deprecated. Run PermissionsSeeder + RolePermissionSeeder instead.');
    }
}
