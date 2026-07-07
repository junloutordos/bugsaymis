<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * CID Competitions & Winnings permissions.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\CompetitionPermissionSeeder --force
 */
class CompetitionPermissionSeeder extends Seeder
{
    /** name => description */
    private const PERMISSIONS = [
        'cid.competitions.view'   => 'View and log competition participation and awards (own records)',
        'cid.competitions.manage' => 'Edit or delete any competition record (CID Chief, Administrator)',
    ];

    private const MANAGE_ROLES = ['Administrator', 'CID Chief'];

    private const VIEW_ROLES = ['Administrator', 'CID Chief', 'AUH', 'Faculty', 'Staff'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module'      => 'CID',
                'description' => $description,
            ]);
        }

        $this->grant(self::MANAGE_ROLES, ['cid.competitions.view', 'cid.competitions.manage']);
        $this->grant(self::VIEW_ROLES,   ['cid.competitions.view']);
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
