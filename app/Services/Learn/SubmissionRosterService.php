<?php

namespace App\Services\Learn;

use App\Models\Learn\Assignment;
use App\Models\Learn\Submission;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the per-assignment grading roster: every enrolled student in the
 * assignment's course, left-joined against their submission (if any) — same
 * shape as Phase 1's CourseResolver/RosterService compute-on-read pattern.
 */
class SubmissionRosterService
{
    /** @return Collection<int, array{student_id:int, name:string, submission_id:?int, status:string}> */
    public function rosterFor(Assignment $assignment): Collection
    {
        $course = $assignment->course();
        if (! $course) {
            return collect();
        }

        $studentIds = StudentEnrollment::where('school_year_id', $course->school_year_id)
            ->where('section_id', $course->section_id)
            ->where('status', 'enrolled')
            ->pluck('student_id')
            ->unique()
            ->values();

        $students = DB::table('students')->whereIn('id', $studentIds)->get(['id', 'lastname', 'firstname'])->keyBy('id');

        $submissions = Submission::where('learn_assignment_id', $assignment->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        return $studentIds->map(function ($studentId) use ($students, $submissions) {
            $student = $students->get($studentId);
            $submission = $submissions->get($studentId);

            return [
                'student_id' => (int) $studentId,
                'name' => $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$studentId}",
                'submission_id' => $submission?->id,
                'status' => $this->statusFor($submission),
            ];
        })->sortBy('name')->values();
    }

    private function statusFor(?Submission $submission): string
    {
        if (! $submission) {
            return 'not_submitted';
        }
        if ($submission->isGraded()) {
            return 'graded';
        }

        return $submission->isLate() ? 'late' : 'submitted';
    }
}
