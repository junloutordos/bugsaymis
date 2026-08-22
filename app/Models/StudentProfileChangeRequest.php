<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfileChangeRequest extends Model
{
    protected $fillable = [
        'student_id', 'requested_changes', 'status',
        'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $casts = [
        'requested_changes' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
