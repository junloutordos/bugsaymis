<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Employee Functions permission.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\EmployeeFunctionsPermissionSeeder --force
 */
class EmployeeFunctionsPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'employee_functions.manage' => 'Assign Core/Support Functions and WDP tags to employees',
    ];

    private const MANAGE_ROLES = ['HR'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module' => 'Employee Functions',
                'description' => $description,
            ]);
        }

        $ids = Permission::whereIn('name', array_keys(self::PERMISSIONS))->pluck('id')->all();
        foreach (self::MANAGE_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }
}
