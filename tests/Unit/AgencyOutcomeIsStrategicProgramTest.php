<?php

namespace Tests\Unit;

use App\Models\AgencyOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOutcomeIsStrategicProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_strategic_functions_outcome_is_a_strategic_program(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);

        $this->assertTrue($outcome->isStrategicProgram());
    }

    public function test_core_functions_outcome_is_not_a_strategic_program(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'Core Functions', 'function_type' => 'Core Functions']);

        $this->assertFalse($outcome->isStrategicProgram());
    }

    public function test_support_functions_outcome_is_not_a_strategic_program(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'Support Functions', 'function_type' => 'Support Functions']);

        $this->assertFalse($outcome->isStrategicProgram());
    }

    public function test_null_function_type_is_not_a_strategic_program(): void
    {
        $outcome = AgencyOutcome::create(['outcome' => 'Untagged']);

        $this->assertFalse($outcome->isStrategicProgram());
    }
}
