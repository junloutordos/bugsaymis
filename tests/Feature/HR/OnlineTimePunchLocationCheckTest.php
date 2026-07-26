<?php

namespace Tests\Feature\HR;

use App\Models\HR\OnlinePunchGeofenceZone;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the CampusPresenceService extraction — locks in that
 * OnlineTimePunchController::locationCheck() returns the same JSON shape
 * after resolveLocationGate() moved out of FaceRecognitionService.
 */
class OnlineTimePunchLocationCheckTest extends TestCase
{
    use RefreshDatabase;

    private function actingSuperAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_location_check_returns_ok_status_shape_when_inside_zone(): void
    {
        $user = $this->actingSuperAdmin();
        OnlinePunchGeofenceZone::create([
            'label' => 'Campus', 'latitude' => 9.0, 'longitude' => 125.5, 'radius_meters' => 250, 'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('hr.online-punch.location-check'), [
            'latitude' => 9.0, 'longitude' => 125.5, 'accuracy' => 20,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['status', 'distance_meters', 'network_status']);
        $response->assertJsonPath('status', 'ok');
    }

    public function test_location_check_returns_outside_status_shape(): void
    {
        $user = $this->actingSuperAdmin();
        OnlinePunchGeofenceZone::create([
            'label' => 'Campus', 'latitude' => 9.0, 'longitude' => 125.5, 'radius_meters' => 250, 'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('hr.online-punch.location-check'), [
            'latitude' => 14.5995, 'longitude' => 120.9842, 'accuracy' => 20,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'outside');
    }
}
