<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Announcements + Certificate of Appearance permissions.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\AdministrationModulesPermissionSeeder --force
 */
class AdministrationModulesPermissionSeeder extends Seeder
{
    /** name => description */
    private const PERMISSIONS = [
        'announcements.manage' => 'Create, publish, and manage announcements',
        'coa.manage'           => 'Issue and manage Certificates of Appearance for external visitors',
    ];

    private const MANAGE_ROLES = ['Administrator', 'OCD', 'Records'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module'      => 'Administration',
                'description' => $description,
            ]);
        }

        $this->grant(self::MANAGE_ROLES, array_keys(self::PERMISSIONS));
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
