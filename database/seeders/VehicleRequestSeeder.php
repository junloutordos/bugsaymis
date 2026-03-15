<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleRequest;
use App\Models\User;
use Carbon\Carbon;

class VehicleRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        if (! $user) {
            $this->command->info('No users found; skipping VehicleRequest seeding.');
            return;
        }

        VehicleRequest::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);
    }
}
