<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        ['module' => 'Student Clearance', 'name' => 'students.clearance.view', 'description' => 'View student year-end clearance records'],
        ['module' => 'Student Clearance', 'name' => 'students.clearance.manage', 'description' => 'Create clearance periods and generate student clearances'],
        ['module' => 'Student Clearance', 'name' => 'students.clearance.sign', 'description' => 'Sign office or administrative student clearance items'],
        ['module' => 'Student Clearance', 'name' => 'students.clearance.subject-sign', 'description' => 'Sign subject teacher clearance items'],
        ['module' => 'Student Clearance', 'name' => 'students.clearance.adviser-review', 'description' => 'Review class or section clearance completion as adviser'],
        ['module' => 'Student Clearance', 'name' => 'students.clearance.registrar', 'description' => 'Receive and finalize student year-end clearances'],
        ['module' => 'Student Clearance', 'name' => 'students.clearance.report', 'description' => 'Generate student clearance completion reports'],
        ['module' => 'Student Clearance', 'name' => 'students.clearance.admin', 'description' => 'Administer all student clearance records and signatories'],
    ];

    private array $rolePermissions = [
        'Registrar' => [
            'students.clearance.view',
            'students.clearance.manage',
            'students.clearance.registrar',
            'students.clearance.report',
        ],
        'Faculty' => [
            'students.clearance.subject-sign',
            'students.clearance.adviser-review',
        ],
        'Staff' => [
            'students.clearance.sign',
        ],
        'CID Chief' => [
            'students.clearance.view',
            'students.clearance.sign',
            'students.clearance.subject-sign',
            'students.clearance.adviser-review',
        ],
        'Nurse' => [
            'students.clearance.sign',
        ],
        'Guidance' => [
            'students.clearance.sign',
        ],
        'Librarian' => [
            'students.clearance.sign',
        ],
        'Student Discipline Officer' => [
            'students.clearance.sign',
        ],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            $existing = DB::table('permissions')->where('name', $permission['name'])->first();

            if ($existing) {
                DB::table('permissions')
                    ->where('id', $existing->id)
                    ->update([
                        'module'      => $permission['module'],
                        'description' => $permission['description'],
                        'updated_at'  => now(),
                    ]);
                continue;
            }

            DB::table('permissions')->insert([
                'name'        => $permission['name'],
                'module'      => $permission['module'],
                'description' => $permission['description'],
                'updated_at'  => now(),
                'created_at'  => now(),
            ]);
        }

        foreach ($this->rolePermissions as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            if (! $roleId) {
                continue;
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('name', $permissionNames)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id'       => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $names = array_column($this->permissions, 'name');
        $permissionIds = DB::table('permissions')->whereIn('name', $names)->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
