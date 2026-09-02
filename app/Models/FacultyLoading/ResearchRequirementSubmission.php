<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchRequirementSubmission extends Model
{
    protected $table = 'research_requirement_submissions';

    protected $fillable = [
        'research_requirement_assignment_id',
        'submitted_by',
        'notes',
        'submitted_at',
        'is_late',
        'review_status',
        'review_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'is_late'      => 'boolean',
        'reviewed_at'  => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ResearchRequirementAssignment::class, 'research_requirement_assignment_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ResearchRequirementSubmissionFile::class);
    }
}
