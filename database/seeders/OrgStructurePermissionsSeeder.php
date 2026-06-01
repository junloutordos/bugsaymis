<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * @deprecated Merged into PermissionsSeeder + RolePermissionSeeder.
 *             Kept as stub to avoid breaking DatabaseSeeder references.
 */
class OrgStructurePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('OrgStructurePermissionsSeeder is deprecated. Run PermissionsSeeder + RolePermissionSeeder instead.');
        return;

        // ── 1. Register all Org Structure permissions ─────────────────────────
        $permissions = [
            // Viewing
            ['module' => 'OrgStructure', 'name' => 'org.view',
             'description' => 'View the organizational chart and unit details'],
            ['module' => 'OrgStructure', 'name' => 'org.view_all',
             'description' => 'View the full org chart including inactive units'],

            // Unit management
            ['module' => 'OrgStructure', 'name' => 'org.units.create',
             'description' => 'Create new organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.units.update',
             'description' => 'Edit existing organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.units.delete',
             'description' => 'Delete / archive organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.units.manage',
             'description' => 'Full CRUD management of organizational units'],

            // Employee assignment management
            ['module' => 'OrgStructure', 'name' => 'org.assign',
             'description' => 'Assign or re-assign employees to organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.assign.manage',
             'description' => 'Full management of employee unit assignments'],

            // Unit head designation
            ['module' => 'OrgStructure', 'name' => 'org.heads.manage',
             'description' => 'Designate and manage unit heads'],

            // Versioning
            ['module' => 'OrgStructure', 'name' => 'org.versions.view',
             'description' => 'View org structure version history'],
            ['module' => 'OrgStructure', 'name' => 'org.versions.manage',
             'description' => 'Create, approve, and activate org structure versions'],

            // Reports & exports
            ['module' => 'OrgStructure', 'name' => 'org.export',
             'description' => 'Export the organizational chart (PDF, PNG, Excel)'],
            ['module' => 'OrgStructure', 'name' => 'org.reports',
             'description' => 'Generate organizational structure reports'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // ── 2. Role assignments ───────────────────────────────────────────────

        $assign = function (string $roleName, array $permissionNames): void {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                return;
            }
            $ids = Permission::whereIn('name', $permissionNames)->pluck('id')->toArray();
            $role->permissions()->syncWithoutDetaching($ids);
        };

        // Administrator: all org permissions (also bypasses via isSuperAdmin())
        $admin = Role::where('name', 'Administrator')->first();
        if ($admin) {
            $allOrgIds = Permission::where('module', 'OrgStructure')->pluck('id')->toArray();
            $admin->permissions()->syncWithoutDetaching($allOrgIds);
        }

        // HR: full management authority
        $assign('HR', [
            'org.view', 'org.view_all',
            'org.units.create', 'org.units.update', 'org.units.delete', 'org.units.manage',
            'org.assign', 'org.assign.manage',
            'org.heads.manage',
            'org.versions.view', 'org.versions.manage',
            'org.export', 'org.reports',
        ]);

        // OCD / DivisionChief: view + export (read-only management insight)
        foreach (['OCD', 'DivisionChief', 'PMT'] as $role) {
            $assign($role, [
                'org.view', 'org.view_all',
                'org.export', 'org.reports',
                'org.versions.view',
            ]);
        }

        // All other employee roles: view only (public org chart)
        $employeeViewRoles = [
            'MIS', 'Payroll Officer', 'HRMPSB', 'Recruitment Officer',
            'Faculty', 'Staff', 'Registrar', 'Records', 'Librarian',
            'Nurse', 'Guidance', 'GSU Head', 'InformationOfficer',
            'Dorm Manager', 'Student', 'Parent',
        ];
        foreach ($employeeViewRoles as $role) {
            $assign($role, ['org.view']);
        }
    }
}
