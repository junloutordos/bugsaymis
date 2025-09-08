<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class InitialRolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'Administrator',
            'DivisionChief',
            'Faculty',
            'Staff',
            'Student',
            'Parent',
            'OCD',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
