<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionGrade extends Model
{
    protected $table = 'learn_discussion_grades';

    protected $fillable = [
        'learn_discussion_id', 'student_id', 'points_earned', 'feedback_comment', 'graded_at', 'graded_by',
    ];

    protected $casts = [
        'points_earned' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'learn_discussion_id');
    }
}
