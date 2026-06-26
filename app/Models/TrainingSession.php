<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TrainingSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'learning_program_id',
        'session_date',
        'start_time',
        'end_time',
        'venue',
        'mode',
        'facilitator',
        'max_participants',
        'status',
        'remarks',
    ];

    protected $casts = [
        'session_date' => 'date:Y-m-d',
        'start_time'   => 'datetime:H:i',
        'end_time'     => 'datetime:H:i',
        'max_participants' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function program(): BelongsTo
    {
        return $this->belongsTo(LearningProgram::class, 'learning_program_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class, 'session_id');
    }

    public function evaluations(): HasManyThrough
    {
        return $this->hasManyThrough(
            TrainingEvaluation::class,
            TrainingParticipant::class,
            'session_id',       // FK on training_participants
            'participant_id',   // FK on training_evaluations
        );
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeUpcoming($query)
    {
        return $query->where('session_date', '>=', now()->toDateString())
                     ->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForProgram($query, int $programId)
    {
        return $query->where('learning_program_id', $programId);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getAttendedCountAttribute(): int
    {
        return $this->participants()->where('attendance_status', 'attended')->count();
    }

    public function getRegisteredCountAttribute(): int
    {
        return $this->participants()->count();
    }

    public function getSlotsAvailableAttribute(): int
    {
        return max(0, $this->max_participants - $this->getRegisteredCountAttribute());
    }

    public function getAttendanceRateAttribute(): float
    {
        $registered = $this->getRegisteredCountAttribute();
        if ($registered === 0) return 0;
        return round(($this->getAttendedCountAttribute() / $registered) * 100, 2);
    }
}
