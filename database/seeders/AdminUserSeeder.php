<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'jtordos@crc.pshs.edu.ph'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('12345678'),
                'role' => 'Administrator',
                'email_verified_at' => now(),
            ]
        );
    }
}

