<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The faculty_loading.research_advisories permission + the Research
 * Coordinator role. PermissionsSeeder already defines this permission, but
 * production never auto-seeds (docker-entrypoint.sh only runs migrations) —
 * run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\ResearchCoordinatorPermissionSeeder --force
 */
class ResearchCoordinatorPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate(
            ['name' => 'faculty_loading.research_advisories'],
            [
                'module'      => 'Faculty Loading',
                'description' => 'Manage Research Advisory groups (create/edit/remove, assign members)',
            ]
        );

        $role = Role::firstOrCreate(
            ['name' => 'Research Coordinator'],
            ['description' => 'Manages Research Advisory groups — thesis/research group assignment and membership']
        );

        $permissionId = Permission::where('name', 'faculty_loading.research_advisories')->value('id');
        $role->permissions()->syncWithoutDetaching([$permissionId]);

        $this->command->info('Research Coordinator role ready with faculty_loading.research_advisories.');
    }
}
