<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\EmployeeFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFunctionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $function = EmployeeFunction::create([
            'user_id' => $user->id,
            'function_type' => EmployeeFunction::TYPE_CORE,
            'source_type' => EmployeeFunction::SOURCE_MANUAL,
            'label' => 'Test Core Function',
        ]);

        $this->assertTrue($function->user->is($user));
    }

    public function test_scope_core_and_support_filter_correctly(): void
    {
        $user = User::factory()->create();
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Core A']);
        EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Support A']);

        $this->assertSame(1, EmployeeFunction::core()->count());
        $this->assertSame(1, EmployeeFunction::support()->count());
    }

    /**
     * source_label distinguishes committee/load/personnel-plan origins
     * for the UI, since every auto-synced row shares the same raw
     * source_type=load_assignment regardless of which Faculty Loading
     * source actually produced it.
     */
    public function test_source_label_distinguishes_auto_synced_origins(): void
    {
        $user = User::factory()->create();

        $manual = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'x']);
        $wdp = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'wdp', 'label' => 'x']);
        $load = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'load_assignment', 'sync_source_key' => 'load_assignment:1', 'label' => 'x']);
        $committee = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'load_assignment', 'sync_source_key' => 'committee_assignment:1', 'label' => 'x']);
        $personnel = EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'load_assignment', 'sync_source_key' => 'personnel_plan:1', 'label' => 'x']);

        $this->assertSame('Manual', $manual->source_label);
        $this->assertSame('Work Distribution Plan', $wdp->source_label);
        $this->assertSame('Load Assignment', $load->source_label);
        $this->assertSame('Committee', $committee->source_label);
        $this->assertSame('Personnel Plan', $personnel->source_label);
    }
}
