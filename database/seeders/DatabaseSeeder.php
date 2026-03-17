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

        // RBAC seeders
        $this->call(RolesSeeder::class);
        $this->call(PermissionsSeeder::class);
        $this->call(RolePermissionSeeder::class);

        // Ensure Administrator role exists
        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);

        // Create Administrator user if not exists
        $adminUser = User::firstOrCreate(
            ['email' => 'jtordos@crc.pshs.edu.ph'],
            [
                'name' => 'Junlou R. Tordos',
                'password' => Hash::make('password123'), // 🔐 change later
                'role_id' => $adminRole->id,
            ]
        );

        // Ensure Administrator role is in the pivot (idempotent)
        $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);

        // ✅ Call the IPCR Weight Seeder
        $this->call(IPCRWeightSeeder::class);
        // Seed sample vehicle requests (optional)
        $this->call(\Database\Seeders\VehicleRequestSeeder::class);
        // Seed document types (ARTA/CSC-based)
        $this->call(DocumentTypeSeeder::class);
        // Seed IPCR rating periods
        $this->call(IPCRRatingPeriodSeeder::class);
    }
}
