<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnAssignmentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_assignments_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_assignments'));
        $this->assertTrue(Schema::hasColumns('learn_assignments', [
            'id', 'title', 'instructions', 'submission_type', 'points_possible', 'due_at',
        ]));
    }

    public function test_learn_rubrics_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_rubrics'));
        $this->assertTrue(Schema::hasColumns('learn_rubrics', ['id', 'learn_assignment_id']));
    }

    public function test_learn_rubric_criteria_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_rubric_criteria'));
        $this->assertTrue(Schema::hasColumns('learn_rubric_criteria', [
            'id', 'learn_rubric_id', 'description', 'max_points', 'position',
        ]));
    }

    public function test_learn_submissions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_submissions'));
        $this->assertTrue(Schema::hasColumns('learn_submissions', [
            'id', 'learn_assignment_id', 'student_id', 'text_body', 'learn_file_id', 'link_url',
            'submitted_at', 'score', 'feedback_comment', 'graded_at', 'graded_by',
        ]));
    }

    public function test_learn_rubric_scores_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_rubric_scores'));
        $this->assertTrue(Schema::hasColumns('learn_rubric_scores', [
            'id', 'learn_submission_id', 'learn_rubric_criterion_id', 'points_earned',
        ]));
    }

    public function test_learn_submissions_one_per_assignment_per_student(): void
    {
        \Illuminate\Support\Facades\DB::table('learn_assignments')->insert([
            'title' => 'A', 'submission_type' => 'text', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('learn_submissions')->insert([
            ['learn_assignment_id' => 1, 'student_id' => 1, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['learn_assignment_id' => 1, 'student_id' => 1, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
