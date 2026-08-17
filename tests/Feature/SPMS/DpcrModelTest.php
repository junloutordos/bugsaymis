<?php

namespace Tests\Feature\SPMS;

use App\Models\Division;
use App\Models\SPMS\Dpcr;
use App\Models\SPMS\Ipcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DpcrModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_draft_status(): void
    {
        $dpcr = Dpcr::factory()->create();

        $this->assertSame(Dpcr::STATUS_DRAFT, $dpcr->status);
    }

    public function test_belongs_to_division_and_ratee(): void
    {
        $division = Division::factory()->create();
        $ratee = User::factory()->create();
        $dpcr = Dpcr::factory()->create(['division_id' => $division->id, 'ratee_user_id' => $ratee->id]);

        $this->assertSame($division->id, $dpcr->division->id);
        $this->assertSame($ratee->id, $dpcr->ratee->id);
    }

    public function test_has_many_ipcrs_via_dpcr_id(): void
    {
        $dpcr = Dpcr::factory()->create();
        Ipcr::factory()->create(['dpcr_id' => $dpcr->id]);

        $this->assertCount(1, $dpcr->fresh()->ipcrs);
    }

    public function test_optional_fields_are_nullable(): void
    {
        $dpcr = Dpcr::factory()->create();

        $this->assertNull($dpcr->reviewer_user_id);
        $this->assertNull($dpcr->rolled_up_rating);
        $this->assertNull($dpcr->override_rating);
        $this->assertNull($dpcr->final_rating);
    }
}
