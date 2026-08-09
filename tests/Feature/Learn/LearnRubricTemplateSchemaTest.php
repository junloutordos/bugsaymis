<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnRubricTemplateSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_rubric_templates_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_rubric_templates'));
        $this->assertTrue(Schema::hasColumns('learn_rubric_templates', ['id', 'user_id', 'name']));
    }

    public function test_learn_rubric_template_criteria_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_rubric_template_criteria'));
        $this->assertTrue(Schema::hasColumns('learn_rubric_template_criteria', [
            'id', 'learn_rubric_template_id', 'description', 'max_points', 'position',
        ]));
    }
}
