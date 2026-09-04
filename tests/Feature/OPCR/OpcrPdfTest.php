<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\OPCR\OpcrSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPdfTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.view'], ['module' => 'OPCR', 'description' => 'opcr.view']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_pdf_renders_for_a_fiscal_year_with_indicators(): void
    {
        $user = $this->viewer();
        OpcrSetting::current()->update([
            'campus_director_name' => 'RAMIL A. SANCHEZ',
            'executive_director_name' => 'RONNALEE N. ORTEZA',
        ]);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create([
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Cohort survival rate',
            'target' => '0.9',
        ]);
        $indicator->divisions()->sync([$division->id]);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '0.8889']);

        $response = $this->actingAs($user)->get(route('opcr.pdf', 2026));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_renders_for_a_fiscal_year_with_no_indicators(): void
    {
        $user = $this->viewer();

        $response = $this->actingAs($user)->get(route('opcr.pdf', 2026));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
