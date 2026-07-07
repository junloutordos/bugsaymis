<?php

namespace App\Models\CID;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $table = 'competitions';

    public const LEVELS = [
        'campus'        => 'Campus Level',
        'inter_campus'  => 'Inter Campus',
        'regional'      => 'Regional',
        'national'      => 'National',
        'international' => 'International',
    ];

    protected $fillable = [
        'title',
        'competition_type',
        'organizer',
        'venue',
        'level',
        'date_from',
        'date_to',
        'represents_pshs',
        'school_year_id',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'date_from'       => 'date:Y-m-d',
        'date_to'         => 'date:Y-m-d',
        'represents_pshs' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function participants(): HasMany
    {
        return $this->hasMany(CompetitionParticipant::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? $this->level;
    }
}
