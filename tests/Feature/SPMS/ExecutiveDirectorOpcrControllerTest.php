<?php

namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Opcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveDirectorOpcrControllerTest extends TestCase
{
    use RefreshDatabase;

    private function executiveDirector(): User
    {
        $role = Role::create(['name' => 'Executive Director']);
        $approve = Permission::create(['name' => 'spms.opcr.approve', 'module' => 'SPMS']);
        $role->permissions()->attach($approve->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_index_lists_pending_opcrs(): void
    {
        $ed = $this->executiveDirector();
        Opcr::factory()->create(['status' => Opcr::STATUS_SUBMITTED_TO_ED]);
        Opcr::factory()->create(['status' => Opcr::STATUS_DRAFT]);

        $response = $this->actingAs($ed)->get('/spms/opcr/ed');

        $response->assertInertia(fn ($page) => $page->component('SPMS/ExecutiveDirectorOpcrIndex')->has('opcrs', 1));
    }

    public function test_approve_transitions_to_terminal_with_final_rating(): void
    {
        $ed = $this->executiveDirector();
        $opcr = Opcr::factory()->create(['status' => Opcr::STATUS_SUBMITTED_TO_ED, 'rolled_up_rating' => 4.0]);

        $this->actingAs($ed)->post("/spms/opcr/ed/{$opcr->id}/approve")->assertRedirect();

        $final = $opcr->fresh();
        $this->assertSame(Opcr::STATUS_ED_APPROVED, $final->status);
        $this->assertNotNull($final->final_rating);
        $this->assertSame($ed->id, $final->approved_by);
    }

    public function test_return_to_sender_requires_reason(): void
    {
        $ed = $this->executiveDirector();
        $opcr = Opcr::factory()->create(['status' => Opcr::STATUS_SUBMITTED_TO_ED]);

        $this->actingAs($ed)->post("/spms/opcr/ed/{$opcr->id}/return", [])->assertSessionHasErrors('reason');

        $this->actingAs($ed)->post("/spms/opcr/ed/{$opcr->id}/return", ['reason' => 'Q4 actuals incomplete'])->assertRedirect();
        $this->assertSame(Opcr::STATUS_RETURNED, $opcr->fresh()->status);
    }

    public function test_show_exposes_override_rating_and_reason_for_review(): void
    {
        $ed = $this->executiveDirector();
        $opcr = Opcr::factory()->create([
            'status' => Opcr::STATUS_SUBMITTED_TO_ED,
            'rolled_up_rating' => 3.0,
            'override_rating' => 5.0,
            'override_reason' => 'Self-set by Campus Director',
        ]);

        $response = $this->actingAs($ed)->get("/spms/opcr/ed/{$opcr->id}");

        $response->assertInertia(fn ($page) => $page->component('SPMS/ExecutiveDirectorOpcrShow')
            ->where('opcr.override_rating', '5.00')
            ->where('opcr.override_reason', 'Self-set by Campus Director'));
    }
}
