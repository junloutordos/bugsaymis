<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call your roles seeder
        $this->call(InitialRolesTableSeeder::class);

        // Ensure Administrator role exists
        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);

        // Create Administrator user if not exists
        User::firstOrCreate(
            ['email' => 'jtordos@crc.pshs.edu.ph'],
            [
                'name' => 'Junlou R. Tordos',
                'password' => Hash::make('password123'), // 🔐 change later
                'role_id' => $adminRole->id,
            ]
        );
    }
}
