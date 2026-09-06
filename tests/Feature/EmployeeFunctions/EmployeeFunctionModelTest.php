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
}
