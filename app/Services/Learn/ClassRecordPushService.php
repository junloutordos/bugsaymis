<?php

namespace App\Services\Learn;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\Learn\Assignment;
use App\Models\Learn\Submission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links a Learn assignment to a pre-existing Class Record assessment and
 * pushes graded scores into it. Never creates, dates, or reschedules a
 * ClassRecordAssessment — the instructor does that themselves through Class
 * Record's own existing WAT-enforced flow. This service only ever selects
 * an already-plotted assessment and writes ClassRecordScore rows.
 */
class ClassRecordPushService
{
    /** @return Collection<int, ClassRecord> */
    public function candidateClassRecords(Assignment $assignment): Collection
    {
        $course = $assignment->course();
        if (! $course) {
            return collect();
        }

        return ClassRecord::active()
            ->where('subject_id', $course->subject_id)
            ->where('section_id', $course->section_id)
            ->where('school_year_id', $course->school_year_id)
            ->with([
                'quarters' => fn ($q) => $q->orderBy('quarter'),
                'quarters.assessments' => fn ($q) => $q->where('is_graded', true)->with('gradingCategory'),
            ])
            ->get();
    }

    public function link(Assignment $assignment, int $assessmentId, User $user): void
    {
        abort_unless($assignment->canEdit($user), 403);

        $assessment = ClassRecordAssessment::with(['gradingCategory', 'quarter.classRecord'])->findOrFail($assessmentId);

        abort_unless(
            $assessment->gradingCategory->canEditOn($assessment->quarter->classRecord, $user),
            403
        );

        $maxScore = $assignment->maxScore();
        if ($maxScore === null || (float) $assessment->max_score !== $maxScore) {
            throw ValidationException::withMessages([
                'class_record_assessment_id' => "The assessment's max score ({$assessment->max_score}) must exactly match this assignment's max score ({$maxScore}) before linking.",
            ]);
        }

        $assignment->update(['class_record_assessment_id' => $assessment->id]);
    }

    /** @return array{pushed: int, skipped: array<int, string>} */
    public function push(Assignment $assignment, User $user): array
    {
        abort_if(! $assignment->class_record_assessment_id, 422, 'Link a Class Record assessment first.');

        $assessment = $assignment->classRecordAssessment()->with(['gradingCategory', 'quarter.classRecord'])->firstOrFail();

        abort_unless($assignment->canEdit($user), 403);
        abort_unless($assessment->gradingCategory->canEditOn($assessment->quarter->classRecord, $user), 403);

        $submissions = Submission::where('learn_assignment_id', $assignment->id)->whereNotNull('graded_at')->get();

        $pushed = 0;
        $skipped = [];

        foreach ($submissions as $submission) {
            $classRecordStudent = ClassRecordStudent::where('class_record_quarter_id', $assessment->class_record_quarter_id)
                ->where('student_id', $submission->student_id)
                ->first();

            if (! $classRecordStudent) {
                $student = DB::table('students')->where('id', $submission->student_id)->first(['lastname', 'firstname']);
                $skipped[] = $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$submission->student_id}";
                continue;
            }

            ClassRecordScore::updateOrCreate(
                ['class_record_student_id' => $classRecordStudent->id, 'class_record_assessment_id' => $assessment->id],
                ['score' => $submission->score]
            );
            $pushed++;
        }

        $assignment->update(['pushed_at' => now()]);

        return ['pushed' => $pushed, 'skipped' => $skipped];
    }
}
