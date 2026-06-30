<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ErrorReportPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate(
            ['name' => 'error-reports.manage'],
            ['module' => 'MIS'],
        );

        $mis = Role::where('name', 'MIS')->first();
        if ($mis) {
            $id = Permission::where('name', 'error-reports.manage')->value('id');
            if ($id) $mis->permissions()->syncWithoutDetaching([$id]);
        }
    }
}
