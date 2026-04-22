<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'placement_id',
        'task_name',
        'description',
        'assigned_to',
        'due_date',
        'status',
        'completion_notes',
        'completed_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function placement()
    {
        return $this->belongsTo(Placement::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
