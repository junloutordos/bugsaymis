<?php

namespace Tests\Feature\SPMS;

use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Dpcr;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use App\Models\SPMS\PerformanceIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DpcrFullWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_dc_to_ocd_lifecycle_with_ipcr_rollup(): void
    {
        $dcRole = Role::create(['name' => 'DivisionChief']);
        $ocdRole = Role::create(['name' => 'OCD']);
        $dcManage = Permission::create(['name' => 'spms.dpcr.manage', 'module' => 'SPMS']);
        $ocdReview = Permission::create(['name' => 'spms.dpcr.review', 'module' => 'SPMS']);
        $ocdApprove = Permission::create(['name' => 'spms.dpcr.approve', 'module' => 'SPMS']);
        $dcRole->permissions()->attach($dcManage->id);
        $ocdRole->permissions()->attach([$ocdReview->id, $ocdApprove->id]);

        $division = Division::factory()->create();
        $divisionChief = User::factory()->create(['division_id' => $division->id]);
        $divisionChief->roles()->attach($dcRole->id);
        $ocd = User::factory()->create();
        $ocd->roles()->attach($ocdRole->id);

        $period = FiscalPeriod::factory()->create(['cadence' => 'semester']);

        // A rated employee IPCR in the division feeds the rollup
        $employee = User::factory()->create(['division_id' => $division->id]);
        $ipcr = Ipcr::factory()->create([
            'user_id' => $employee->id, 'fiscal_period_id' => $period->id, 'rated_at' => now(),
        ]);
        IpcrTarget::factory()->create([
            'ipcr_id' => $ipcr->id, 'function_type' => 'core', 'rating_avg' => 4.0, 'weight_pct' => 100,
        ]);

        $dpcr = Dpcr::factory()->create([
            'division_id' => $division->id, 'fiscal_period_id' => $period->id, 'ratee_user_id' => $divisionChief->id,
        ]);

        $indicator = PerformanceIndicator::factory()->create();
        $indicator->divisions()->attach($division->id);

        // 1. DC generates targets and logs a quarterly actual
        $this->actingAs($divisionChief)->post("/spms/dpcr/{$dpcr->id}/generate-targets")->assertRedirect();
        $target = $dpcr->fresh()->targets->first();
        $this->actingAs($divisionChief)->post("/spms/dpcr/{$dpcr->id}/update-targets", [
            'targets' => [$target->id => ['q1_actual' => 25, 'remarks' => 'On track']],
        ])->assertRedirect();
        $this->assertEquals(25, (float) $target->fresh()->q1_actual);

        // 2. DC submits to reviewer (OCD)
        $this->actingAs($divisionChief)->post("/spms/dpcr/{$dpcr->id}/submit-to-reviewer")->assertRedirect();
        $this->assertSame(Dpcr::STATUS_SUBMITTED_TO_REVIEWER, $dpcr->fresh()->status);

        // 3. OCD reviews -> rollup rating computed from the division's rated IPCR
        $this->actingAs($ocd)->post("/spms/dpcr/review/{$dpcr->id}/review")->assertRedirect();
        $reviewed = $dpcr->fresh();
        $this->assertSame(Dpcr::STATUS_REVIEWED, $reviewed->status);
        $this->assertEqualsWithDelta(4.0, (float) $reviewed->rolled_up_rating, 0.001);

        // 4. OCD submits to approver, then approves — terminal
        $this->actingAs($ocd)->post("/spms/dpcr/review/{$dpcr->id}/submit-to-approver")->assertRedirect();
        $this->actingAs($ocd)->post("/spms/dpcr/review/{$dpcr->id}/approve")->assertRedirect();
        $final = $dpcr->fresh();
        $this->assertSame(Dpcr::STATUS_APPROVED, $final->status);
        $this->assertEqualsWithDelta(4.0, (float) $final->final_rating, 0.001);
        $this->assertSame('Very Satisfactory', $final->final_adjectival);

        // Terminal: no further DC action is accepted
        $this->actingAs($divisionChief)->post("/spms/dpcr/{$dpcr->id}/submit-to-reviewer")->assertStatus(500);
    }
}
