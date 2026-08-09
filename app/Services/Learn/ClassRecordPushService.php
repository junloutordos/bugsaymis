<?php

namespace App\Services\Learn;

use App\Contracts\Learn\HasClassRecordLink;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links a Learn gradable item (Assignment or Quiz) to a pre-existing Class Record assessment
 * and pushes graded scores into it. Never creates, dates, or reschedules a
 * ClassRecordAssessment — the instructor does that themselves through Class Record's own
 * existing WAT-enforced flow. This service only ever selects an already-plotted assessment
 * and writes ClassRecordScore rows.
 */
class ClassRecordPushService
{
    /** @return Collection<int, ClassRecord> */
    public function candidateClassRecords(HasClassRecordLink $item): Collection
    {
        $course = $item->course();
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

    public function link(HasClassRecordLink $item, int $assessmentId, User $user): void
    {
        abort_unless($item->canEdit($user), 403);

        $assessment = ClassRecordAssessment::with(['gradingCategory', 'quarter.classRecord'])->findOrFail($assessmentId);

        abort_unless(
            $assessment->gradingCategory->canEditOn($assessment->quarter->classRecord, $user),
            403
        );

        $maxScore = $item->maxScore();
        if ($maxScore === null || (float) $assessment->max_score !== $maxScore) {
            throw ValidationException::withMessages([
                'class_record_assessment_id' => "The assessment's max score ({$assessment->max_score}) must exactly match this item's max score ({$maxScore}) before linking.",
            ]);
        }

        $item->update(['class_record_assessment_id' => $assessment->id]);
    }

    /** @return array{pushed: int, skipped: array<int, string>} */
    public function push(HasClassRecordLink $item, User $user): array
    {
        abort_if(! $item->class_record_assessment_id, 422, 'Link a Class Record assessment first.');

        $assessment = $item->classRecordAssessment()->with(['gradingCategory', 'quarter.classRecord'])->firstOrFail();

        abort_unless($item->canEdit($user), 403);
        abort_unless($assessment->gradingCategory->canEditOn($assessment->quarter->classRecord, $user), 403);

        $scores = $item->gradedStudentScores();

        $pushed = 0;
        $skipped = [];

        foreach ($scores as $studentId => $score) {
            $classRecordStudent = ClassRecordStudent::where('class_record_quarter_id', $assessment->class_record_quarter_id)
                ->where('student_id', $studentId)
                ->first();

            if (! $classRecordStudent) {
                $student = DB::table('students')->where('id', $studentId)->first(['lastname', 'firstname']);
                $skipped[] = $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$studentId}";
                continue;
            }

            ClassRecordScore::updateOrCreate(
                ['class_record_student_id' => $classRecordStudent->id, 'class_record_assessment_id' => $assessment->id],
                ['score' => $score]
            );
            $pushed++;
        }

        $item->update(['pushed_at' => now()]);

        return ['pushed' => $pushed, 'skipped' => $skipped];
    }
}
