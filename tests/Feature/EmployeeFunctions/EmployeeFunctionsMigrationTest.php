<?php

namespace Tests\Feature\EmployeeFunctions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeFunctionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_functions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('employee_functions'));
        $this->assertTrue(Schema::hasColumns('employee_functions', [
            'id', 'user_id', 'function_type', 'source_type',
            'load_assignment_id', 'work_distribution_plan_id',
            'label', 'weight_percent', 'academic_term_id', 'created_by',
            'created_at', 'updated_at',
        ]));
    }
}
