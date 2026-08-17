<?php

namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Opcr;
use App\Models\SPMS\OpcrTarget;
use App\Models\SPMS\PerformanceIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusDirectorOpcrControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ratee(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::create(['name' => 'spms.opcr.manage', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_index_lists_only_own_opcrs(): void
    {
        $ratee = $this->ratee();
        Opcr::factory()->create(['ratee_user_id' => $ratee->id]);
        Opcr::factory()->create();

        $response = $this->actingAs($ratee)->get('/spms/opcr');

        $response->assertInertia(fn ($page) => $page->component('SPMS/CampusDirectorOpcrIndex')->has('opcrs', 1));
    }

    public function test_non_ratee_cannot_view_show(): void
    {
        $this->ratee();
        $opcr = Opcr::factory()->create();
        $other = User::factory()->create();
        $other->roles()->attach(Role::where('name', 'OCD')->first()->id);

        $response = $this->actingAs($other)->get("/spms/opcr/{$opcr->id}");

        $response->assertStatus(403);
    }

    public function test_generate_targets_creates_rows_from_all_indicators(): void
    {
        $ratee = $this->ratee();
        $opcr = Opcr::factory()->create(['ratee_user_id' => $ratee->id]);
        PerformanceIndicator::factory()->create();
        PerformanceIndicator::factory()->create();

        $this->actingAs($ratee)->post("/spms/opcr/{$opcr->id}/generate-targets")->assertRedirect();

        $this->assertSame(2, $opcr->fresh()->targets()->count());
    }

    public function test_update_targets_writes_quarterly_actuals(): void
    {
        $ratee = $this->ratee();
        $opcr = Opcr::factory()->create(['ratee_user_id' => $ratee->id]);
        $target = OpcrTarget::factory()->create(['opcr_id' => $opcr->id]);

        $this->actingAs($ratee)->post("/spms/opcr/{$opcr->id}/update-targets", [
            'targets' => [$target->id => ['q1_actual' => 40, 'remarks' => 'Campus-wide on track']],
        ])->assertRedirect();

        $fresh = $target->fresh();
        $this->assertEquals(40, (float) $fresh->q1_actual);
        $this->assertSame('Campus-wide on track', $fresh->remarks);
    }

    public function test_submit_to_ed_transitions_status(): void
    {
        $ratee = $this->ratee();
        $opcr = Opcr::factory()->create(['ratee_user_id' => $ratee->id, 'status' => Opcr::STATUS_DRAFT]);

        $this->actingAs($ratee)->post("/spms/opcr/{$opcr->id}/submit-to-ed")->assertRedirect();

        $this->assertSame(Opcr::STATUS_SUBMITTED_TO_ED, $opcr->fresh()->status);
    }

    public function test_store_creates_opcr_for_current_annual_period(): void
    {
        $ratee = $this->ratee();
        FiscalPeriod::factory()->create(['cadence' => 'annual', 'is_current' => true]);

        $this->actingAs($ratee)->post('/spms/opcr')->assertRedirect();

        $this->assertDatabaseHas('spms_opcrs', ['ratee_user_id' => $ratee->id]);
    }

    public function test_store_redirects_to_existing_opcr_instead_of_duplicating(): void
    {
        $ratee = $this->ratee();
        $period = FiscalPeriod::factory()->create(['cadence' => 'annual', 'is_current' => true]);
        $existing = Opcr::factory()->create(['fiscal_period_id' => $period->id]);

        $this->actingAs($ratee)->post('/spms/opcr')->assertRedirect(route('spms.opcr.show', $existing->id));

        $this->assertSame(1, Opcr::where('fiscal_period_id', $period->id)->count());
    }

    public function test_store_errors_when_no_current_annual_period_exists(): void
    {
        $ratee = $this->ratee();

        $this->actingAs($ratee)->post('/spms/opcr')->assertSessionHasErrors('fiscal_period');
    }
}
