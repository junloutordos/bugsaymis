<?php

namespace Tests\Feature\SPMS;

use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Dpcr;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Opcr;
use App\Models\SPMS\PerformanceIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrFullWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_ocd_to_ed_lifecycle_with_dpcr_rollup(): void
    {
        $ocdRole = Role::create(['name' => 'OCD']);
        $edRole = Role::create(['name' => 'Executive Director']);
        $opcrManage = Permission::create(['name' => 'spms.opcr.manage', 'module' => 'SPMS']);
        $opcrApprove = Permission::create(['name' => 'spms.opcr.approve', 'module' => 'SPMS']);
        $ocdRole->permissions()->attach($opcrManage->id);
        $edRole->permissions()->attach($opcrApprove->id);

        $campusDirector = User::factory()->create();
        $campusDirector->roles()->attach($ocdRole->id);
        $executiveDirector = User::factory()->create();
        $executiveDirector->roles()->attach($edRole->id);

        $annualPeriod = FiscalPeriod::factory()->create(['cadence' => 'annual', 'fiscal_year' => 2026]);
        $semester = FiscalPeriod::factory()->create(['cadence' => 'semester', 'parent_period_id' => $annualPeriod->id]);

        // An approved division DPCR feeds the OPCR rollup
        $division = Division::factory()->create();
        User::factory()->count(2)->create(['division_id' => $division->id]);
        Dpcr::factory()->create([
            'division_id' => $division->id, 'fiscal_period_id' => $semester->id,
            'status' => Dpcr::STATUS_APPROVED, 'final_rating' => 4.75,
        ]);

        $opcr = Opcr::factory()->create([
            'fiscal_period_id' => $annualPeriod->id, 'ratee_user_id' => $campusDirector->id,
        ]);

        PerformanceIndicator::factory()->create(['fiscal_year' => 2026]);

        // 1. Campus Director generates targets and logs a quarterly actual
        $this->actingAs($campusDirector)->post("/spms/opcr/{$opcr->id}/generate-targets")->assertRedirect();
        $target = $opcr->fresh()->targets->first();
        $this->actingAs($campusDirector)->post("/spms/opcr/{$opcr->id}/update-targets", [
            'targets' => [$target->id => ['q1_actual' => 90, 'remarks' => 'Campus-wide on track']],
        ])->assertRedirect();
        $this->assertEquals(90, (float) $target->fresh()->q1_actual);

        // 2. Campus Director submits to Executive Director -> rollup computed from approved DPCRs
        $this->actingAs($campusDirector)->post("/spms/opcr/{$opcr->id}/submit-to-ed")->assertRedirect();
        $submitted = $opcr->fresh();
        $this->assertSame(Opcr::STATUS_SUBMITTED_TO_ED, $submitted->status);
        $this->assertEqualsWithDelta(4.75, (float) $submitted->rolled_up_rating, 0.001);

        // 3. Executive Director approves — terminal
        $this->actingAs($executiveDirector)->post("/spms/opcr/ed/{$opcr->id}/approve")->assertRedirect();
        $final = $opcr->fresh();
        $this->assertSame(Opcr::STATUS_ED_APPROVED, $final->status);
        $this->assertEqualsWithDelta(4.75, (float) $final->final_rating, 0.001);
        $this->assertSame('Outstanding', $final->final_adjectival);
        $this->assertSame($executiveDirector->id, $final->approved_by);

        // Terminal: no further Campus Director action is accepted
        $this->actingAs($campusDirector)->post("/spms/opcr/{$opcr->id}/submit-to-ed")->assertStatus(500);
    }
}
