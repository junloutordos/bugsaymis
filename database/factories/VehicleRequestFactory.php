<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\VehicleRequest;
use App\Models\User;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleRequest>
 */
class VehicleRequestFactory extends Factory
{
    protected $model = VehicleRequest::class;

    public function definition()
    {
        return [
            'requestor_id' => User::factory(),
            'purpose' => $this->faker->sentence(6),
            'destination' => $this->faker->city(),
            'date_needed' => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'vehicle_type' => $this->faker->randomElement(['Van', 'Car', 'Pickup']),
            'passengers' => $this->faker->numberBetween(1, 12),
            'status' => 'Pending',
        ];
    }
}
