<?php

namespace Tests\Unit\SPMS;

use App\Models\Division;
use App\Models\SPMS\WeightProfile;
use App\Services\SPMS\WeightProfileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightProfileResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_back_to_hardcoded_default_when_nothing_seeded(): void
    {
        $weights = (new WeightProfileResolver())->resolve('ipcr', null, 2026);

        $this->assertSame(30.0, $weights['strategic_pct']);
        $this->assertSame(50.0, $weights['core_pct']);
        $this->assertSame(20.0, $weights['support_pct']);
    }

    public function test_uses_system_default_profile_when_no_division_override(): void
    {
        WeightProfile::factory()->create([
            'level' => 'ipcr', 'division_id' => null, 'fiscal_year' => 2026,
            'strategic_pct' => 25, 'core_pct' => 55, 'support_pct' => 20,
        ]);

        $weights = (new WeightProfileResolver())->resolve('ipcr', 999, 2026);

        $this->assertSame(25.0, $weights['strategic_pct']);
    }

    public function test_division_specific_profile_overrides_system_default(): void
    {
        $division = Division::factory()->create();
        WeightProfile::factory()->create([
            'level' => 'ipcr', 'division_id' => null, 'fiscal_year' => 2026,
            'strategic_pct' => 30, 'core_pct' => 50, 'support_pct' => 20,
        ]);
        WeightProfile::factory()->create([
            'level' => 'ipcr', 'division_id' => $division->id, 'fiscal_year' => 2026,
            'strategic_pct' => 20, 'core_pct' => 60, 'support_pct' => 20,
        ]);

        $weights = (new WeightProfileResolver())->resolve('ipcr', $division->id, 2026);

        $this->assertSame(20.0, $weights['strategic_pct']);
        $this->assertSame(60.0, $weights['core_pct']);
    }
}
