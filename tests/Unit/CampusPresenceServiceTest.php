<?php

namespace Tests\Unit;

use App\Models\HR\OnlinePunchGeofenceZone;
use App\Models\HR\OnlinePunchNetworkRule;
use App\Services\CampusPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusPresenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CampusPresenceService
    {
        return app(CampusPresenceService::class);
    }

    public function test_unconfigured_when_no_zones_or_rules_exist(): void
    {
        $gate = $this->service()->resolveLocationGate(9.0, 125.5, 10.0, '203.0.113.5');

        $this->assertSame('ok', $gate['status']);
        $this->assertSame('unconfigured', $gate['geofence']['status']);
    }

    public function test_ok_when_inside_zone_with_precise_fix(): void
    {
        OnlinePunchGeofenceZone::create([
            'label' => 'Campus', 'latitude' => 9.0, 'longitude' => 125.5, 'radius_meters' => 250, 'is_active' => true,
        ]);

        $gate = $this->service()->resolveLocationGate(9.0, 125.5, 20.0, '203.0.113.5');

        $this->assertSame('ok', $gate['status']);
        $this->assertSame('inside', $gate['geofence']['status']);
    }

    public function test_outside_when_far_from_every_zone(): void
    {
        OnlinePunchGeofenceZone::create([
            'label' => 'Campus', 'latitude' => 9.0, 'longitude' => 125.5, 'radius_meters' => 250, 'is_active' => true,
        ]);

        $gate = $this->service()->resolveLocationGate(14.5995, 120.9842, 20.0, '203.0.113.5'); // Manila, far away

        $this->assertSame('outside', $gate['status']);
    }

    public function test_no_permission_when_coordinates_missing(): void
    {
        OnlinePunchGeofenceZone::create([
            'label' => 'Campus', 'latitude' => 9.0, 'longitude' => 125.5, 'radius_meters' => 250, 'is_active' => true,
        ]);

        $gate = $this->service()->resolveLocationGate(null, null, null, '203.0.113.5');

        $this->assertSame('no_permission', $gate['status']);
    }

    public function test_coarse_when_accuracy_too_wide_and_network_not_trusted(): void
    {
        OnlinePunchGeofenceZone::create([
            'label' => 'Campus', 'latitude' => 9.0, 'longitude' => 125.5, 'radius_meters' => 250, 'is_active' => true,
        ]);

        $gate = $this->service()->resolveLocationGate(9.0, 125.5, 500.0, '203.0.113.5');

        $this->assertSame('coarse', $gate['status']);
    }

    public function test_ok_when_coarse_fix_but_network_trusted(): void
    {
        OnlinePunchGeofenceZone::create([
            'label' => 'Campus', 'latitude' => 9.0, 'longitude' => 125.5, 'radius_meters' => 250, 'is_active' => true,
        ]);
        OnlinePunchNetworkRule::create([
            'label' => 'Campus WiFi', 'cidr' => '203.0.113.0/24', 'is_active' => true,
        ]);

        $gate = $this->service()->resolveLocationGate(9.0, 125.5, 500.0, '203.0.113.5');

        $this->assertSame('ok', $gate['status']);
        $this->assertSame('trusted', $gate['networkStatus']);
    }
}
