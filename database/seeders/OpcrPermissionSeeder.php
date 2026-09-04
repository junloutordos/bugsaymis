<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * OPCR module permissions.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\OpcrPermissionSeeder --force
 */
class OpcrPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'opcr.view' => 'View the campus OPCR (Office Performance Commitment and Review)',
        'opcr.manage' => 'Create/edit OPCR periods, indicators, targets, actuals, and ratings',
    ];

    private const MANAGE_ROLES = ['OCD', 'PMT'];

    private const VIEW_ONLY_ROLES = ['DivisionChief'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module' => 'OPCR',
                'description' => $description,
            ]);
        }

        $this->grant(self::MANAGE_ROLES, ['opcr.view', 'opcr.manage']);
        $this->grant(self::VIEW_ONLY_ROLES, ['opcr.view']);
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
