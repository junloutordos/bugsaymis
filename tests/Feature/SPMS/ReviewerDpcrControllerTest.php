<?php

namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Dpcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerDpcrControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ocd(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $review = Permission::create(['name' => 'spms.dpcr.review', 'module' => 'SPMS']);
        $approve = Permission::create(['name' => 'spms.dpcr.approve', 'module' => 'SPMS']);
        $role->permissions()->attach([$review->id, $approve->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_index_lists_pending_dpcrs(): void
    {
        $ocd = $this->ocd();
        Dpcr::factory()->create(['status' => Dpcr::STATUS_SUBMITTED_TO_REVIEWER]);
        Dpcr::factory()->create(['status' => Dpcr::STATUS_DRAFT]);

        $response = $this->actingAs($ocd)->get('/spms/dpcr/review');

        $response->assertInertia(fn ($page) => $page->component('SPMS/ReviewerDpcrIndex')->has('dpcrs', 1));
    }

    public function test_review_then_approve_full_reviewer_flow(): void
    {
        $ocd = $this->ocd();
        $dpcr = Dpcr::factory()->create(['status' => Dpcr::STATUS_SUBMITTED_TO_REVIEWER]);

        $this->actingAs($ocd)->post("/spms/dpcr/review/{$dpcr->id}/review")->assertRedirect();
        $this->assertSame(Dpcr::STATUS_REVIEWED, $dpcr->fresh()->status);

        $this->actingAs($ocd)->post("/spms/dpcr/review/{$dpcr->id}/submit-to-approver")->assertRedirect();
        $this->assertSame(Dpcr::STATUS_SUBMITTED_TO_APPROVER, $dpcr->fresh()->status);

        $this->actingAs($ocd)->post("/spms/dpcr/review/{$dpcr->id}/approve")->assertRedirect();
        $final = $dpcr->fresh();
        $this->assertSame(Dpcr::STATUS_APPROVED, $final->status);
        $this->assertNotNull($final->final_rating);
    }

    public function test_return_to_sender_requires_reason(): void
    {
        $ocd = $this->ocd();
        $dpcr = Dpcr::factory()->create(['status' => Dpcr::STATUS_SUBMITTED_TO_REVIEWER]);

        $this->actingAs($ocd)->post("/spms/dpcr/review/{$dpcr->id}/return", [])->assertSessionHasErrors('reason');

        $this->actingAs($ocd)->post("/spms/dpcr/review/{$dpcr->id}/return", ['reason' => 'Missing Q2 actuals'])->assertRedirect();
        $this->assertSame(Dpcr::STATUS_RETURNED, $dpcr->fresh()->status);
    }
}
