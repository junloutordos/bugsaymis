<?php

namespace Tests\Unit;

use App\Services\AtlasSentinelFleetStateService;
use Carbon\Carbon;
use Tests\TestCase;

class AtlasSentinelFleetStateServiceTest extends TestCase
{
    private AtlasSentinelFleetStateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AtlasSentinelFleetStateService();
        Carbon::setTestNow(Carbon::parse('2026-07-06 08:52:33'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_offers_only_newer_versions(): void
    {
        $this->assertTrue($this->service->shouldOfferUpdate('1.1.1', '1.0.8.0'));
        $this->assertFalse($this->service->shouldOfferUpdate('1.1.1', '1.1.1.0'));
        $this->assertFalse($this->service->shouldOfferUpdate('1.1.1', '1.2.0.0'));
    }

    public function test_it_classifies_checkin_and_update_state(): void
    {
        $this->assertSame('active_outdated', $this->service->classify(now()->subMinutes(5), '1.0.8.0', '1.1.1'));
        $this->assertSame('stale', $this->service->classify(now()->subMinutes(58), '1.0.8.0', '1.1.1'));
        $this->assertSame('offline', $this->service->classify(now()->subMinutes(121), '1.0.9.0', '1.1.1'));
        $this->assertSame('unknown_version', $this->service->classify(now()->subMinute(), null, '1.1.1'));
        $this->assertSame('current', $this->service->classify(now()->subMinute(), '1.1.1.0', '1.1.1'));
    }

    public function test_minutes_since_checkin_is_positive_for_past_checkins(): void
    {
        $this->assertSame(58, $this->service->minutesSinceCheckin(now()->subMinutes(58)));
    }

    public function test_update_offer_slots_are_capped_per_window(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        for ($i = 1; $i <= AtlasSentinelFleetStateService::MAX_OFFERS_PER_WINDOW; $i++) {
            $this->assertTrue($this->service->tryReserveUpdateOfferSlot('9.9.9'), "offer {$i} should be allowed");
        }

        $this->assertFalse($this->service->tryReserveUpdateOfferSlot('9.9.9'));

        // A different release version gets its own budget.
        $this->assertTrue($this->service->tryReserveUpdateOfferSlot('9.9.10'));
    }
}
