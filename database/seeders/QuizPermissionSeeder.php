<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class QuizPermissionSeeder extends Seeder
{
    // Same broad staff list AMS activities.manage is granted to — quizzes
    // attach to Activities and Class Records, so any role that can own one
    // of those should be able to build/host a quiz.
    private const MANAGE_ROLES = [
        'MIS', 'Payroll Officer', 'HRMPSB', 'Recruitment Officer',
        'DivisionChief', 'OCD', 'PMT', 'CID Chief', 'Faculty', 'Staff', 'Registrar',
        'Records', 'Librarian', 'Nurse', 'Guidance', 'GSU Head', 'FAD Chief',
        'InformationOfficer', 'Dorm Manager', 'HR',
    ];

    private const VIEW_ALL_ROLES = ['HR', 'MIS'];

    public function run(): void
    {
        Permission::firstOrCreate(['name' => 'quiz.manage'], ['module' => 'Quiz']);
        Permission::firstOrCreate(['name' => 'quiz.view_all'], ['module' => 'Quiz']);

        $manageId = Permission::where('name', 'quiz.manage')->value('id');
        $viewAllId = Permission::where('name', 'quiz.view_all')->value('id');

        foreach (self::MANAGE_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && $manageId) {
                $role->permissions()->syncWithoutDetaching([$manageId]);
            }
        }

        foreach (self::VIEW_ALL_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && $viewAllId) {
                $role->permissions()->syncWithoutDetaching([$viewAllId]);
            }
        }
    }
}
