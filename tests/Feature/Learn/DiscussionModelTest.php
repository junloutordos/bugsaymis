<?php

namespace Tests\Feature\Learn;

use App\Contracts\Learn\HasClassRecordLink;
use App\Models\Learn\Discussion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_discussion_implements_has_class_record_link(): void
    {
        $this->assertInstanceOf(HasClassRecordLink::class, new Discussion());
    }

    public function test_discussion_has_nested_posts(): void
    {
        $discussion = Discussion::create(['title' => 'Week 1 Discussion', 'prompt' => 'Discuss chapter 1.']);
        $top = $discussion->posts()->create([
            'author_type' => 'student', 'author_id' => 111, 'body' => 'My thoughts.',
        ]);
        $reply = $discussion->posts()->create([
            'parent_post_id' => $top->id, 'author_type' => 'faculty', 'author_id' => 222, 'body' => 'Good point.',
        ]);

        $this->assertCount(2, $discussion->fresh()->posts);
        $this->assertSame($top->id, $reply->fresh()->parentPost->id);
        $this->assertCount(1, $top->fresh()->replies);
        $this->assertFalse($top->isDeleted());
    }

    public function test_max_score_and_graded_student_scores(): void
    {
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P', 'points_possible' => 10]);
        $this->assertSame(10.0, $discussion->maxScore());

        $discussion->grades()->create(['student_id' => 111, 'points_earned' => 8]);
        $discussion->grades()->create(['student_id' => 222]); // ungraded — must be excluded

        $this->assertSame([111 => 8.0], $discussion->gradedStudentScores());
    }
}
