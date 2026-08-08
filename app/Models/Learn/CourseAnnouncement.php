<?php

namespace App\Models\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseAnnouncement extends Model
{
    protected $table = 'learn_course_announcements';

    protected $fillable = ['learn_course_id', 'title', 'body', 'posted_by', 'posted_at'];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'learn_course_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
