<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchRequirementAssignment extends Model
{
    protected $table = 'research_requirement_assignments';

    protected $fillable = [
        'research_requirement_id',
        'research_group_id',
        'status',
        'excluded',
        'reminder_sent_at',
        'overdue_notified_at',
    ];

    protected $casts = [
        'excluded'             => 'boolean',
        'reminder_sent_at'     => 'datetime',
        'overdue_notified_at'  => 'datetime',
    ];

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ResearchRequirement::class, 'research_requirement_id');
    }

    public function researchGroup(): BelongsTo
    {
        return $this->belongsTo(ResearchGroup::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ResearchRequirementSubmission::class)->latest('submitted_at');
    }

    public function scopeVisible($query)
    {
        return $query->where('excluded', false);
    }
}
