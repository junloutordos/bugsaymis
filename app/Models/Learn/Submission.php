<?php

namespace App\Models\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    protected $table = 'learn_submissions';

    protected $fillable = [
        'learn_assignment_id', 'student_id', 'text_body', 'learn_file_id', 'link_url',
        'submitted_at', 'score', 'feedback_comment', 'graded_at', 'graded_by',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'submitted_at' => 'datetime',
        'score' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'learn_assignment_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'learn_file_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function rubricScores(): HasMany
    {
        return $this->hasMany(RubricScore::class, 'learn_submission_id');
    }

    public function isLate(): bool
    {
        if (! $this->assignment->due_at) {
            return false;
        }

        return $this->submitted_at->gt($this->assignment->due_at);
    }

    public function isGraded(): bool
    {
        return $this->graded_at !== null;
    }
}
