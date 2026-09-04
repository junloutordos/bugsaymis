<?php

namespace Tests\Feature\VehicleRequests;

use App\Models\User;
use App\Models\VehicleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleRequestSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_by_purpose_does_not_error(): void
    {
        $requester = User::factory()->create();

        VehicleRequest::factory()->create([
            'requestor_id' => $requester->id,
            'purpose'      => 'Field trip to Butuan City',
        ]);

        $response = $this->actingAs($requester)
            ->get(route('vehicle-requests.index', ['search' => 'Field trip']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('VehicleRequests/Index')
            ->has('requests.data', 1)
        );
    }

    public function test_search_by_driver_name_does_not_error(): void
    {
        $requester = User::factory()->create();
        $driver = User::factory()->create(['name' => 'Juan Dela Cruz']);

        VehicleRequest::factory()->create([
            'requestor_id' => $requester->id,
            'driver_id'    => $driver->id,
        ]);

        $response = $this->actingAs($requester)
            ->get(route('vehicle-requests.index', ['search' => 'Juan Dela Cruz']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('VehicleRequests/Index')
            ->has('requests.data', 1)
        );
    }
}
