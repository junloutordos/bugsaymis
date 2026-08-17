<?php

namespace Tests\Feature\SPMS;

use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Opcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_draft_status(): void
    {
        $opcr = Opcr::factory()->create();

        $this->assertSame(Opcr::STATUS_DRAFT, $opcr->status);
    }

    public function test_belongs_to_fiscal_period_and_ratee(): void
    {
        $period = FiscalPeriod::factory()->create(['cadence' => 'annual']);
        $ratee = User::factory()->create();
        $opcr = Opcr::factory()->create(['fiscal_period_id' => $period->id, 'ratee_user_id' => $ratee->id]);

        $this->assertSame($period->id, $opcr->fiscalPeriod->id);
        $this->assertSame($ratee->id, $opcr->ratee->id);
    }

    public function test_optional_fields_are_nullable(): void
    {
        $opcr = Opcr::factory()->create();

        $this->assertNull($opcr->approver_user_id);
        $this->assertNull($opcr->rolled_up_rating);
        $this->assertNull($opcr->override_rating);
        $this->assertNull($opcr->final_rating);
        $this->assertNull($opcr->approved_by);
    }

    public function test_has_many_targets(): void
    {
        $opcr = Opcr::factory()->create();
        \App\Models\SPMS\OpcrTarget::factory()->create(['opcr_id' => $opcr->id]);

        $this->assertCount(1, $opcr->fresh()->targets);
    }
}
