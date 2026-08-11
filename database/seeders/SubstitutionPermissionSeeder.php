<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * hr.substitution.approve / hr.substitution.revoke — admin-override
 * permissions for the Substitution module. Identity-based approve/revoke
 * (the employee's resolved AUH/Division Chief, or the employee themselves
 * for revoke) is authorized without these permissions — they exist only so
 * Administrators and Division Chiefs generally can act as a backstop.
 */
class SubstitutionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $approve = Permission::updateOrCreate(
            ['name' => 'hr.substitution.approve'],
            ['module' => 'HR', 'description' => 'Approve/reject substitution nominations (admin override)']
        );
        $revoke = Permission::updateOrCreate(
            ['name' => 'hr.substitution.revoke'],
            ['module' => 'HR', 'description' => 'Revoke an active substitution (admin override)']
        );

        foreach (['Administrator', 'DivisionChief'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $role?->permissions()->syncWithoutDetaching([$approve->id, $revoke->id]);
        }
    }
}
