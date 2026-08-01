<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassScheduleDayAdjustment extends Model
{
    protected $fillable = [
        'academic_term_id',
        'postponed_from_date',
        'effective_date',
        'adjustment_type',
        'ceremony_start_time',
        'ceremony_end_time',
        'shift_minutes',
        'reason',
        'status',
        'schedule_snapshot',
        'created_by',
        'published_by',
        'published_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'postponed_from_date' => 'date:Y-m-d',
        'effective_date' => 'date:Y-m-d',
        'shift_minutes' => 'integer',
        'schedule_snapshot' => 'array',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('effective_date', $date);
    }
}
