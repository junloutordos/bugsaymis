<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SPMSPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'spms.ipcr.manage' => [
            'description' => 'Create and manage own SPMS IPCR (targets, MOV checklist, submit for rating)',
            'roles' => ['Administrator', 'Faculty', 'Staff'],
        ],
        'spms.ipcr.review' => [
            'description' => 'Review/rate SPMS IPCRs (Division Chief, PMT, HR, Director stages)',
            'roles' => ['Administrator', 'DivisionChief', 'HR', 'PMT'],
        ],
        'spms.admin.manage' => [
            'description' => 'Configure SPMS weight profiles, fiscal periods, and MOV document types',
            'roles' => ['Administrator', 'HR', 'PMT'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $config) {
            $permission = Permission::updateOrCreate(
                ['name' => $name],
                ['module' => 'SPMS', 'description' => $config['description']]
            );

            foreach ($config['roles'] as $roleName) {
                $role = Role::where('name', $roleName)->first();
                $role?->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }
    }
}
