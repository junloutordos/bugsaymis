<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RhPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Register all RH permissions ───────────────────────────────────
        $permissions = [
            ['module' => 'ResidenceHall', 'name' => 'rh.dashboard.view',
             'description' => 'View the Residence Hall dashboard'],
            ['module' => 'ResidenceHall', 'name' => 'rh.rooms.manage',
             'description' => 'Manage room catalog (BRH/GRH)'],
            ['module' => 'ResidenceHall', 'name' => 'rh.applications.view',
             'description' => 'View RH accommodation applications'],
            ['module' => 'ResidenceHall', 'name' => 'rh.applications.evaluate',
             'description' => 'Evaluate and recommend RH applications (RH Committee)'],
            ['module' => 'ResidenceHall', 'name' => 'rh.applications.approve',
             'description' => 'Approve or reject RH applications (Campus Director)'],
            ['module' => 'ResidenceHall', 'name' => 'rh.interns.view',
             'description' => 'View the intern roster'],
            ['module' => 'ResidenceHall', 'name' => 'rh.interns.manage',
             'description' => 'Manage room assignments, check-in/out, and contracts'],
            ['module' => 'ResidenceHall', 'name' => 'rh.leave-passes.view',
             'description' => 'View RH student leave passes'],
            ['module' => 'ResidenceHall', 'name' => 'rh.leave-passes.approve',
             'description' => 'Approve or reject student leave passes'],
            ['module' => 'ResidenceHall', 'name' => 'rh.leave-passes.guard',
             'description' => 'Log departure and arrival times on leave passes (Guard)'],
            ['module' => 'ResidenceHall', 'name' => 'rh.housekeeping.manage',
             'description' => 'Fill and submit Good Housekeeping Checklists'],
            ['module' => 'ResidenceHall', 'name' => 'rh.incidents.manage',
             'description' => 'Log and manage health/behavioral incidents'],
            ['module' => 'ResidenceHall', 'name' => 'rh.fees.manage',
             'description' => 'Manage the monthly fee ledger'],
            ['module' => 'ResidenceHall', 'name' => 'rh.reports.view',
             'description' => 'Access and print RH reports'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // ── 2. Create Dorm Manager role ───────────────────────────────────────
        $dormManager = Role::firstOrCreate(['name' => 'Dorm Manager']);

        // ── 3. Assign permissions to roles ────────────────────────────────────
        $assign = function (string $roleName, array $permissionNames): void {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                return;
            }
            $ids = Permission::whereIn('name', $permissionNames)->pluck('id')->toArray();
            $role->permissions()->syncWithoutDetaching($ids);
        };

        $allRhPermissions = array_column($permissions, 'name');

        // Dorm Manager — all RH permissions
        $assign('Dorm Manager', $allRhPermissions);

        // Administrator — all RH permissions (also bypasses via isSuperAdmin())
        $admin = Role::where('name', 'Administrator')->first();
        if ($admin) {
            $allIds = Permission::where('module', 'ResidenceHall')->pluck('id')->toArray();
            $admin->permissions()->syncWithoutDetaching($allIds);
        }

        // SSD Chief — read/view + evaluation endorsement
        $assign('SSD Chief', [
            'rh.dashboard.view',
            'rh.applications.view',
            'rh.applications.evaluate',
            'rh.interns.view',
            'rh.leave-passes.view',
            'rh.reports.view',
        ]);

        // OCD (Campus Director) — approve applications
        $assign('OCD', [
            'rh.dashboard.view',
            'rh.applications.view',
            'rh.applications.approve',
            'rh.interns.view',
            'rh.reports.view',
        ]);
    }
}
