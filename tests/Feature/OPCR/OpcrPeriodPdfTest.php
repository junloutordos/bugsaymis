<?php

namespace Tests\Feature\OPCR;

use App\Models\Division;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\OPCR\OpcrPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPeriodPdfTest extends TestCase
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

    public function test_pdf_renders_for_a_period_with_indicators(): void
    {
        $user = $this->viewer();
        $period = OpcrPeriod::create([
            'fiscal_year' => 2026,
            'period_label' => 'January - December 2026',
            'campus_director_name' => 'RAMIL A. SANCHEZ',
            'executive_director_name' => 'RONNALEE N. ORTEZA',
        ]);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $indicator = OpcrIndicator::create([
            'opcr_period_id' => $period->id,
            'description' => 'Cohort survival rate',
            'target' => '0.9',
        ]);
        $indicator->divisions()->sync([$division->id]);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '0.8889']);

        $response = $this->actingAs($user)->get(route('opcr-periods.pdf', $period));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_renders_for_an_empty_period(): void
    {
        $user = $this->viewer();
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'January - December 2026']);

        $response = $this->actingAs($user)->get(route('opcr-periods.pdf', $period));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
