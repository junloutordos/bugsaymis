<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * executive.dashboard.view — the Executive Dashboard for top management.
 * Prod never auto-seeds: run manually via ECS exec after deploy.
 */
class ExecutiveDashboardPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::updateOrCreate(
            ['name' => 'executive.dashboard.view'],
            [
                'module'      => 'executive',
                'description' => 'View the Executive Dashboard (cross-system analytics for top management)',
            ]
        );

        foreach (['Administrator', 'OCD', 'DivisionChief'] as $roleName) {
            Role::where('name', $roleName)->first()
                ?->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }
}
