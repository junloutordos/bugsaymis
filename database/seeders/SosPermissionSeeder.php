<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * SOS Emergency Button permissions.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\SosPermissionSeeder --force
 */
class SosPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $trigger = Permission::firstOrCreate(['name' => 'sos.trigger'], [
            'module'      => 'SOS',
            'description' => 'Trigger an SOS emergency alert.',
        ]);

        $respond = Permission::firstOrCreate(['name' => 'sos.respond'], [
            'module'      => 'SOS',
            'description' => 'View the SOS Command Center and acknowledge/triage/resolve alerts.',
        ]);

        $manage = Permission::firstOrCreate(['name' => 'sos.manage'], [
            'module'      => 'SOS',
            'description' => 'Configure SOS escalation tiers, external contacts, and thresholds.',
        ]);

        foreach (['DRRM Coordinator', 'Security Guard', 'Administrator', 'Nurse'] as $roleName) {
            Role::where('name', $roleName)->first()
                ?->permissions()->syncWithoutDetaching([$respond->id]);
        }

        Role::where('name', 'Administrator')->first()
            ?->permissions()->syncWithoutDetaching([$manage->id, $trigger->id]);
    }
}
