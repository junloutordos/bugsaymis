<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PPMPPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'ppmp.create',      'module' => 'ppmp', 'description' => 'Create and edit own unit PPMP'],
            ['name' => 'ppmp.submit',      'module' => 'ppmp', 'description' => 'Submit PPMP for review'],
            ['name' => 'ppmp.review',      'module' => 'ppmp', 'description' => 'View submitted PPMPs from all units'],
            ['name' => 'ppmp.approve',     'module' => 'ppmp', 'description' => 'Approve or return PPMPs'],
            ['name' => 'ppmp.consolidate', 'module' => 'ppmp', 'description' => 'Consolidate PPMPs into APP'],
            ['name' => 'ppmp.export',      'module' => 'ppmp', 'description' => 'Export PPMP/APP to Excel/PDF'],
            ['name' => 'ppmp.view_all',    'module' => 'ppmp', 'description' => 'View all PPMPs across units'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['module' => $perm['module'], 'description' => $perm['description']]
            );
        }

        // Assign defaults to existing roles (idempotent)
        $this->assignToRole('Faculty', ['ppmp.create', 'ppmp.submit', 'ppmp.export']);
        $this->assignToRole('Staff', ['ppmp.create', 'ppmp.submit', 'ppmp.export']);
    }

    private function assignToRole(string $roleName, array $permissionNames): void
    {
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            return;
        }

        $permIds = Permission::whereIn('name', $permissionNames)->pluck('id');
        $role->permissions()->syncWithoutDetaching($permIds);
    }
}
