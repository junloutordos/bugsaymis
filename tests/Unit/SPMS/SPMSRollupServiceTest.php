<?php

namespace Tests\Unit\SPMS;

use App\Models\Division;
use App\Models\SPMS\Dpcr;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use App\Models\User;
use App\Services\SPMS\SPMSRollupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SPMSRollupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_no_rated_ipcrs(): void
    {
        $dpcr = Dpcr::factory()->create();

        $this->assertNull((new SPMSRollupService())->rollupIpcrsToDpcr($dpcr));
    }

    public function test_computes_load_weighted_average_of_rated_core_targets(): void
    {
        $division = Division::factory()->create();
        $period = FiscalPeriod::factory()->create(['cadence' => 'semester']);
        $dpcr = Dpcr::factory()->create(['division_id' => $division->id, 'fiscal_period_id' => $period->id]);

        $employeeA = User::factory()->create(['division_id' => $division->id]);
        $ipcrA = Ipcr::factory()->create([
            'user_id' => $employeeA->id, 'fiscal_period_id' => $period->id, 'rated_at' => now(),
        ]);
        IpcrTarget::factory()->create([
            'ipcr_id' => $ipcrA->id, 'function_type' => 'core', 'rating_avg' => 5.0, 'weight_pct' => 60,
        ]);

        $employeeB = User::factory()->create(['division_id' => $division->id]);
        $ipcrB = Ipcr::factory()->create([
            'user_id' => $employeeB->id, 'fiscal_period_id' => $period->id, 'rated_at' => now(),
        ]);
        IpcrTarget::factory()->create([
            'ipcr_id' => $ipcrB->id, 'function_type' => 'core', 'rating_avg' => 3.0, 'weight_pct' => 40,
        ]);

        // (5*60 + 3*40) / (60+40) = 420/100 = 4.2
        $this->assertEqualsWithDelta(4.2, (new SPMSRollupService())->rollupIpcrsToDpcr($dpcr->fresh()), 0.001);
    }

    public function test_backfills_dpcr_id_on_qualifying_ipcrs(): void
    {
        $division = Division::factory()->create();
        $period = FiscalPeriod::factory()->create(['cadence' => 'semester']);
        $dpcr = Dpcr::factory()->create(['division_id' => $division->id, 'fiscal_period_id' => $period->id]);

        $employee = User::factory()->create(['division_id' => $division->id]);
        $ipcr = Ipcr::factory()->create([
            'user_id' => $employee->id, 'fiscal_period_id' => $period->id, 'rated_at' => now(),
        ]);
        IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'rating_avg' => 4.0]);

        (new SPMSRollupService())->rollupIpcrsToDpcr($dpcr->fresh());

        $this->assertSame($dpcr->id, $ipcr->fresh()->dpcr_id);
    }

    public function test_does_not_overwrite_an_existing_dpcr_link(): void
    {
        $division = Division::factory()->create();
        $period = FiscalPeriod::factory()->create(['cadence' => 'semester']);
        $dpcr = Dpcr::factory()->create(['division_id' => $division->id, 'fiscal_period_id' => $period->id]);
        $otherDpcr = Dpcr::factory()->create(['division_id' => $division->id, 'fiscal_period_id' => $period->id]);

        $employee = User::factory()->create(['division_id' => $division->id]);
        $ipcr = Ipcr::factory()->create([
            'user_id' => $employee->id, 'fiscal_period_id' => $period->id, 'rated_at' => now(),
            'dpcr_id' => $otherDpcr->id,
        ]);
        IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id, 'function_type' => 'core', 'rating_avg' => 4.0]);

        (new SPMSRollupService())->rollupIpcrsToDpcr($dpcr->fresh());

        $this->assertSame($otherDpcr->id, $ipcr->fresh()->dpcr_id);
    }

    public function test_ignores_ipcrs_outside_division_or_period(): void
    {
        $division = Division::factory()->create();
        $otherDivision = Division::factory()->create();
        $period = FiscalPeriod::factory()->create(['cadence' => 'semester']);
        $otherPeriod = FiscalPeriod::factory()->create(['cadence' => 'semester']);
        $dpcr = Dpcr::factory()->create(['division_id' => $division->id, 'fiscal_period_id' => $period->id]);

        $outsideEmployee = User::factory()->create(['division_id' => $otherDivision->id]);
        $outsideIpcr = Ipcr::factory()->create([
            'user_id' => $outsideEmployee->id, 'fiscal_period_id' => $period->id, 'rated_at' => now(),
        ]);
        IpcrTarget::factory()->create(['ipcr_id' => $outsideIpcr->id, 'function_type' => 'core', 'rating_avg' => 5.0]);

        $wrongPeriodEmployee = User::factory()->create(['division_id' => $division->id]);
        $wrongPeriodIpcr = Ipcr::factory()->create([
            'user_id' => $wrongPeriodEmployee->id, 'fiscal_period_id' => $otherPeriod->id, 'rated_at' => now(),
        ]);
        IpcrTarget::factory()->create(['ipcr_id' => $wrongPeriodIpcr->id, 'function_type' => 'core', 'rating_avg' => 5.0]);

        $this->assertNull((new SPMSRollupService())->rollupIpcrsToDpcr($dpcr->fresh()));
    }

    public function test_ignores_unrated_ipcrs(): void
    {
        $division = Division::factory()->create();
        $period = FiscalPeriod::factory()->create(['cadence' => 'semester']);
        $dpcr = Dpcr::factory()->create(['division_id' => $division->id, 'fiscal_period_id' => $period->id]);

        $employee = User::factory()->create(['division_id' => $division->id]);
        $unratedIpcr = Ipcr::factory()->create([
            'user_id' => $employee->id, 'fiscal_period_id' => $period->id, 'rated_at' => null,
        ]);
        IpcrTarget::factory()->create(['ipcr_id' => $unratedIpcr->id, 'function_type' => 'core', 'rating_avg' => 5.0]);

        $this->assertNull((new SPMSRollupService())->rollupIpcrsToDpcr($dpcr->fresh()));
    }
}
