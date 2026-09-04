<?php

namespace Tests\Feature\OPCR;

use App\Models\Division;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrCloneTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_clone_copies_tagging_target_budget_and_divisions_but_resets_actuals_and_rating(): void
    {
        $user = $this->manager();
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);

        $source = OpcrIndicator::create([
            'fiscal_year' => 2026,
            'description' => 'Cohort survival rate',
            'target' => '0.9',
            'budget' => 5000,
            'rating_quality' => 5,
            'rating_average' => 5,
        ]);
        $source->divisions()->sync([$division->id]);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $source->id, 'quarter' => 1, 'value' => '0.5']);

        $response = $this->actingAs($user)->post(route('opcr.clone'), [
            'source_fiscal_year' => 2026,
            'target_fiscal_year' => 2027,
        ]);

        $response->assertRedirect();
        $cloned = OpcrIndicator::forFiscalYear(2027)->first();
        $this->assertNotNull($cloned);
        $this->assertEquals('Cohort survival rate', $cloned->description);
        $this->assertEquals('0.9', $cloned->target);
        $this->assertEquals(5000, $cloned->budget);
        $this->assertTrue($cloned->divisions->contains($division));
        $this->assertNull($cloned->rating_quality);
        $this->assertCount(0, $cloned->actuals);
    }

    public function test_clone_is_rejected_when_target_fiscal_year_already_has_indicators(): void
    {
        $user = $this->manager();
        OpcrIndicator::create(['fiscal_year' => 2026, 'description' => 'Source']);
        OpcrIndicator::create(['fiscal_year' => 2027, 'description' => 'Already here']);

        $response = $this->actingAs($user)->post(route('opcr.clone'), [
            'source_fiscal_year' => 2026,
            'target_fiscal_year' => 2027,
        ]);

        $response->assertSessionHasErrors('source_fiscal_year');
    }

    public function test_clone_rejects_source_and_target_being_the_same_year(): void
    {
        $user = $this->manager();

        $response = $this->actingAs($user)->post(route('opcr.clone'), [
            'source_fiscal_year' => 2026,
            'target_fiscal_year' => 2026,
        ]);

        $response->assertSessionHasErrors('source_fiscal_year');
    }
}
