<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'hr.service-records.view' => 'View employee service records',
        'hr.service-records.view-own' => 'View own verified service record',
        'hr.service-records.manage' => 'Create and update service record entries',
        'hr.service-records.verify' => 'Verify service record entries and evidence',
        'hr.service-records.certify' => 'Certify and issue official service records',
        'hr.service-records.export' => 'Export service record reports',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'module' => 'HR Service Records',
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $this->grant(['Administrator', 'HR'], array_keys(self::PERMISSIONS));
        $this->grant(
            ['Faculty', 'Staff', 'DivisionChief', 'OCD', 'MIS', 'Payroll Officer'],
            ['hr.service-records.view-own'],
        );
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    private function grant(array $roleNames, array $permissionNames): void
    {
        $roleIds = DB::table('roles')->whereIn('name', $roleNames)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }
};
