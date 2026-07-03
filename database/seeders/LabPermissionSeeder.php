<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Science Laboratory Management permissions + the Science Research Assistant role.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\LabPermissionSeeder --force
 */
class LabPermissionSeeder extends Seeder
{
    /** name => description */
    private const PERMISSIONS = [
        'lab.view'                => 'View the Science Laboratory Management module and dashboard',
        'lab.request'             => 'File laboratory reservation, equipment and reagent requests',
        'lab.manage'              => 'Manage laboratories, equipment, inventory, maintenance, calibration, safety and waste (SRS/SRA)',
        'lab.calibration.approve' => 'Approve laboratory equipment calibration schedules (AUH/CID Chief)',
        'lab.reports'             => 'View laboratory inventory and management reports',
    ];

    // SRS/SRA operational role — full management of the module.
    private const MANAGE_ROLES = ['Administrator', 'Science Research Assistant'];

    // Academic leadership — view + approve calibration + reports.
    private const APPROVER_ROLES = ['Administrator', 'CID Chief', 'AUH'];

    // Anyone who may request lab use.
    private const REQUEST_ROLES = [
        'Administrator', 'Science Research Assistant', 'CID Chief', 'AUH',
        'Faculty', 'Staff', 'OCD',
    ];

    private const VIEW_ROLES = [
        'Administrator', 'Science Research Assistant', 'CID Chief', 'AUH', 'OCD',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module'      => 'Science Laboratory',
                'description' => $description,
            ]);
        }

        // Ensure the SRS/SRA role exists.
        Role::firstOrCreate(['name' => 'Science Research Assistant'], [
            'description' => 'Science Research Specialist / Assistant — manages science laboratories',
        ]);

        $this->grant(self::MANAGE_ROLES,   ['lab.view', 'lab.request', 'lab.manage', 'lab.reports']);
        $this->grant(self::APPROVER_ROLES, ['lab.view', 'lab.request', 'lab.calibration.approve', 'lab.reports']);
        $this->grant(self::REQUEST_ROLES,  ['lab.request']);
        $this->grant(self::VIEW_ROLES,     ['lab.view']);
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
