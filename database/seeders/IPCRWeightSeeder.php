<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Division;

class IPCRWeightSeeder extends Seeder
{
    public function run(): void
    {
        $weights = [
            'Curriculum and Instructions Division' => ['strategic' => 30, 'core' => 55, 'support' => 15],
            'Finance and Administrative Division' => ['strategic' => 30, 'core' => 40, 'support' => 30],
            'Student Services Division' => ['strategic' => 30, 'core' => 40, 'support' => 30],
            'Office of the Campus Director' => ['strategic' => 30, 'core' => 40, 'support' => 30],
        ];

        foreach ($weights as $divisionName => $weightData) {
            $division = Division::where('division_name', $divisionName)->first();

            if ($division) {
                // Insert only if it doesn't exist
                $exists = DB::table('ipcr_weight_distributions')
                    ->where('division_id', $division->id)
                    ->exists();

                if (!$exists) {
                    DB::table('ipcr_weight_distributions')->insert([
                        'division_id' => $division->id,
                        'strategic' => $weightData['strategic'],
                        'core' => $weightData['core'],
                        'support' => $weightData['support'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
