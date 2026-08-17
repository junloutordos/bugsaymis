<?php

namespace Tests\Feature\SPMS;

use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Dpcr;
use App\Models\SPMS\DpcrTarget;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\PerformanceIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionChiefDpcrControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ratee(): User
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $permission = Permission::create(['name' => 'spms.dpcr.manage', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_index_lists_only_own_dpcrs(): void
    {
        $ratee = $this->ratee();
        Dpcr::factory()->create(['ratee_user_id' => $ratee->id]);
        Dpcr::factory()->create();

        $response = $this->actingAs($ratee)->get('/spms/dpcr');

        $response->assertInertia(fn ($page) => $page->component('SPMS/DivisionChiefDpcrIndex')->has('dpcrs', 1));
    }

    public function test_non_ratee_cannot_view_show(): void
    {
        $this->ratee();
        $dpcr = Dpcr::factory()->create();
        $other = User::factory()->create();
        $other->roles()->attach(Role::where('name', 'DivisionChief')->first()->id);

        $response = $this->actingAs($other)->get("/spms/dpcr/{$dpcr->id}");

        $response->assertStatus(403);
    }

    public function test_generate_targets_creates_rows_from_division_indicators(): void
    {
        $ratee = $this->ratee();
        $dpcr = Dpcr::factory()->create(['ratee_user_id' => $ratee->id]);
        $indicator = PerformanceIndicator::factory()->create();
        $indicator->divisions()->attach($dpcr->division_id);

        $this->actingAs($ratee)->post("/spms/dpcr/{$dpcr->id}/generate-targets")->assertRedirect();

        $this->assertSame(1, $dpcr->fresh()->targets()->count());
    }

    public function test_update_targets_writes_quarterly_actuals(): void
    {
        $ratee = $this->ratee();
        $dpcr = Dpcr::factory()->create(['ratee_user_id' => $ratee->id]);
        $target = DpcrTarget::factory()->create(['dpcr_id' => $dpcr->id]);

        $this->actingAs($ratee)->post("/spms/dpcr/{$dpcr->id}/update-targets", [
            'targets' => [$target->id => ['q1_actual' => 25.5, 'remarks' => 'On track']],
        ])->assertRedirect();

        $fresh = $target->fresh();
        $this->assertEquals(25.5, (float) $fresh->q1_actual);
        $this->assertSame('On track', $fresh->remarks);
    }

    public function test_submit_to_reviewer_transitions_status(): void
    {
        $ratee = $this->ratee();
        $dpcr = Dpcr::factory()->create(['ratee_user_id' => $ratee->id, 'status' => Dpcr::STATUS_DRAFT]);

        $this->actingAs($ratee)->post("/spms/dpcr/{$dpcr->id}/submit-to-reviewer")->assertRedirect();

        $this->assertSame(Dpcr::STATUS_SUBMITTED_TO_REVIEWER, $dpcr->fresh()->status);
    }

    public function test_store_creates_dpcr_for_own_division_and_current_semester_period(): void
    {
        $ratee = $this->ratee();
        $division = Division::factory()->create();
        $ratee->update(['division_id' => $division->id]);
        FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($ratee)->post('/spms/dpcr')->assertRedirect();

        $this->assertDatabaseHas('spms_dpcrs', ['division_id' => $division->id, 'ratee_user_id' => $ratee->id]);
    }

    public function test_store_redirects_to_existing_dpcr_instead_of_duplicating(): void
    {
        $ratee = $this->ratee();
        $division = Division::factory()->create();
        $ratee->update(['division_id' => $division->id]);
        $period = FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);
        $existing = Dpcr::factory()->create(['division_id' => $division->id, 'fiscal_period_id' => $period->id]);

        $this->actingAs($ratee)->post('/spms/dpcr')->assertRedirect(route('spms.dpcr.show', $existing->id));

        $this->assertSame(1, Dpcr::where('division_id', $division->id)->count());
    }

    public function test_store_errors_when_actor_has_no_division(): void
    {
        $ratee = $this->ratee();
        FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($ratee)->post('/spms/dpcr')->assertSessionHasErrors('division');
    }

    public function test_store_errors_when_no_current_semester_period_exists(): void
    {
        $ratee = $this->ratee();
        $division = Division::factory()->create();
        $ratee->update(['division_id' => $division->id]);

        $this->actingAs($ratee)->post('/spms/dpcr')->assertSessionHasErrors('fiscal_period');
    }
}
