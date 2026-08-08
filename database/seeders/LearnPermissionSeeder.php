<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Learn (LMS) module permissions.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\LearnPermissionSeeder --force
 */
class LearnPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'learn.course.view.all' => 'View every Learn course regardless of teaching assignment (admin/AUH oversight)',
        'learn.admin' => 'Manage Learn module-wide settings',
    ];

    private const VIEW_ALL_ROLES = ['Administrator', 'AUH', 'CID Chief'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module' => 'Learn',
                'description' => $description,
            ]);
        }

        $this->grant(self::VIEW_ALL_ROLES, ['learn.course.view.all']);
        $this->grant(['Administrator'], ['learn.admin']);
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
