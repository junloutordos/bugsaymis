<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\SosEscalationTier;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SosDefaultEscalationTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosDefaultEscalationTierSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_two_tiers_for_each_alert_type(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(SosDefaultEscalationTierSeeder::class);

        foreach (['medical', 'security', 'fire_disaster', 'general'] as $type) {
            $this->assertSame(2, SosEscalationTier::where('alert_type', $type)->count());
            $this->assertDatabaseHas('sos_escalation_tiers', ['alert_type' => $type, 'order' => 2, 'notify_external' => true]);
        }

        $medicalTier1 = SosEscalationTier::where('alert_type', 'medical')->where('order', 1)->first();
        $this->assertSame('Nurse', $medicalTier1->role->name);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(SosDefaultEscalationTierSeeder::class);
        $this->seed(SosDefaultEscalationTierSeeder::class);

        $this->assertSame(2, SosEscalationTier::where('alert_type', 'general')->count());
    }
}
