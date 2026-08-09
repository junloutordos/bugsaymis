<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnAssignmentClassRecordLinkSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_assignments_has_class_record_link_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('learn_assignments', [
            'class_record_assessment_id', 'pushed_at',
        ]));
    }
}
