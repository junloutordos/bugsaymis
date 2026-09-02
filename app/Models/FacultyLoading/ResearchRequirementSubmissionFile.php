<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchRequirementSubmissionFile extends Model
{
    protected $table = 'research_requirement_submission_files';

    protected $fillable = [
        'research_requirement_submission_id',
        'original_filename',
        's3_key',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchRequirementSubmission::class, 'research_requirement_submission_id');
    }
}
