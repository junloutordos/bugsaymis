<?php

namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerIpcrControllerTest extends TestCase
{
    use RefreshDatabase;

    private function reviewer(): User
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $permission = Permission::create(['name' => 'spms.ipcr.review', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $reviewer = User::factory()->create();
        $reviewer->roles()->attach($role->id);

        return $reviewer;
    }

    public function test_index_lists_ipcrs_pending_review(): void
    {
        $reviewer = $this->reviewer();
        Ipcr::factory()->create(['status' => Ipcr::STATUS_TARGET_SUBMITTED]);
        Ipcr::factory()->create(['status' => Ipcr::STATUS_DRAFT_TARGET]);

        $response = $this->actingAs($reviewer)->get('/spms/ipcr/review');

        $response->assertInertia(fn ($page) => $page
            ->component('SPMS/ReviewerIpcrIndex')
            ->has('ipcrs', 1)
        );
    }

    public function test_approve_target_transitions_status(): void
    {
        $reviewer = $this->reviewer();
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_TARGET_SUBMITTED]);

        $this->actingAs($reviewer)->post("/spms/ipcr/review/{$ipcr->id}/approve-target")
            ->assertRedirect();

        $this->assertSame(Ipcr::STATUS_TARGET_APPROVED, $ipcr->fresh()->status);
    }

    public function test_rate_accepts_per_target_scores(): void
    {
        $reviewer = $this->reviewer();
        $ipcr = Ipcr::factory()->create(['status' => Ipcr::STATUS_SUBMITTED_FOR_RATING]);
        $target = IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->actingAs($reviewer)->post("/spms/ipcr/review/{$ipcr->id}/rate", [
            'ratings' => [$target->id => ['rating_q' => 5, 'rating_e' => 4, 'rating_t' => 5]],
        ])->assertRedirect();

        $this->assertSame(Ipcr::STATUS_RATED, $ipcr->fresh()->status);
        $this->assertNotNull($target->fresh()->rating_avg);
    }
}
