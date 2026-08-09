<?php

namespace Tests\Feature\Learn;

use App\Contracts\Learn\HasClassRecordLink;
use App\Models\Learn\Assignment;
use App\Models\Learn\Quiz;
use App\Models\Learn\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasClassRecordLinkContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_and_quiz_both_implement_the_contract(): void
    {
        $this->assertInstanceOf(HasClassRecordLink::class, new Assignment());
        $this->assertInstanceOf(HasClassRecordLink::class, new Quiz());
    }

    public function test_assignment_graded_student_scores_reads_from_graded_submissions_only(): void
    {
        $assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 10]);
        $student = User::factory()->create();

        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 111,
            'text_body' => 'x', 'submitted_at' => now(), 'score' => 8, 'graded_at' => now(), 'graded_by' => $student->id,
        ]);
        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 222,
            'text_body' => 'x', 'submitted_at' => now(),
        ]);

        $this->assertSame([111 => 8.0], $assignment->gradedStudentScores());
    }
}
