<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Lost & Found (GSU custody) permission.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\LostFoundPermissionSeeder --force
 */
class LostFoundPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'lostfound.manage'], [
            'module'      => 'Facilities',
            'description' => 'Lost & Found: receive turnovers, custody, matching, release (GSU / Admin)',
        ]);

        foreach (['Administrator', 'GSU Head'] as $roleName) {
            Role::where('name', $roleName)->first()
                ?->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }
}
