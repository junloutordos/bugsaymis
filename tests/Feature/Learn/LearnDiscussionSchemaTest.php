<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnDiscussionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_discussions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_discussions'));
        $this->assertTrue(Schema::hasColumns('learn_discussions', [
            'id', 'title', 'prompt', 'points_possible', 'class_record_assessment_id', 'pushed_at',
        ]));
    }

    public function test_learn_discussion_posts_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_discussion_posts'));
        $this->assertTrue(Schema::hasColumns('learn_discussion_posts', [
            'id', 'learn_discussion_id', 'parent_post_id', 'author_type', 'author_id',
            'body', 'is_deleted', 'deleted_by_type', 'deleted_by_id',
        ]));
    }

    public function test_learn_discussion_grades_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_discussion_grades'));
        $this->assertTrue(Schema::hasColumns('learn_discussion_grades', [
            'id', 'learn_discussion_id', 'student_id', 'points_earned',
            'feedback_comment', 'graded_at', 'graded_by',
        ]));
    }
}
