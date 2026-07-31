<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['alp.view', 'View ALP programs and assigned records'],
        ['alp.manage', 'Manage ALP reference data and annual cycles'],
        ['alp.advise', 'Manage an assigned ALP as adviser'],
        ['alp.coordinate', 'Review and monitor all ALP programs'],
        ['alp.registrar-certify', 'Certify academic standing of ALP officers'],
        ['alp.approve', 'Recommend or approve ALP accreditation and activities'],
        ['alp.reports', 'View and export consolidated ALP reports'],
        ['alp.audit', 'View ALP compliance and audit history'],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as [$name, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['module' => 'ALP', 'description' => $description, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $this->assign(['Administrator', 'CID Chief'], array_column(self::PERMISSIONS, 0));
        $this->assign(['Faculty'], ['alp.view', 'alp.advise']);
        $this->assign(['Registrar'], ['alp.view', 'alp.registrar-certify']);
        $this->assign(['OCD'], ['alp.view', 'alp.approve']);

        $categoryId = DB::table('designation_categories')->where('code', 'COORD')->value('id');
        if ($categoryId) {
            DB::table('designations')->updateOrInsert(
                ['code' => 'COORD-ALP'],
                [
                    'designation_category_id' => $categoryId,
                    'name' => 'Alternative Learning Program Coordinator',
                    'description' => 'Coordinates ALP accreditation, monitoring, reports, and completion requirements',
                    'load_units' => 3,
                    'assignment_type' => 'admin',
                    'requires_unit' => false,
                    'max_holders' => 1,
                    'sort_order' => 16,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        $names = array_column(self::PERMISSIONS, 0);
        $ids = DB::table('permissions')->whereIn('name', $names)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        DB::table('designations')->where('code', 'COORD-ALP')->delete();
    }

    private function assign(array $roles, array $permissions): void
    {
        $roleIds = DB::table('roles')->whereIn('name', $roles)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }
};
