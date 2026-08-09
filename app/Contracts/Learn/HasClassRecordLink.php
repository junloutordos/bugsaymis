<?php

namespace App\Contracts\Learn;

use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\Learn\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Implemented by any Learn gradable item (Assignment, Quiz) that can be linked to a
 * pre-existing Class Record assessment and have its scores pushed into it. Implementers
 * never create or date a ClassRecordAssessment themselves — see ClassRecordPushService.
 */
interface HasClassRecordLink
{
    public function course(): ?Course;

    public function canEdit(User $user): bool;

    public function maxScore(): ?float;

    public function classRecordAssessment(): BelongsTo;

    /** @return array<int, float> student_id => the score to push for that student. */
    public function gradedStudentScores(): array;
}
