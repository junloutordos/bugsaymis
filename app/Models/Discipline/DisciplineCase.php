<?php

namespace App\Models\Discipline;

use App\Models\Student;
use App\Models\User;
use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplineCase extends Model
{
    use HasApprovalSnapshots;

    protected $fillable = [
        'case_no',
        'student_id',
        'filer_id',
        'filer_position_snapshot',
        'filer_section_snapshot',
        'offense_id',
        'offense_level',
        'nature_of_offense',
        'incident_date',
        'incident_time',
        'place',
        'other_witnesses',
        'narrative',
        'attachments_note',
        'interventions_done',
        'status',
        'date_filed',
        'time_filed',
        'received_by',
        'received_at',
        'resolution',
        'sanction',
        'resolved_by',
        'resolved_at',
        'is_bullying',
        'threat_level',
        'parent_notified_at',
        'respond_by',
        'school_year_id',
    ];

    protected $casts = [
        'incident_date'      => 'date:Y-m-d',
        'date_filed'         => 'date:Y-m-d',
        'received_at'        => 'datetime',
        'resolved_at'        => 'datetime',
        'parent_notified_at' => 'datetime',
        'respond_by'         => 'datetime',
        'is_bullying'        => 'boolean',
        'offense_level'      => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function offense(): BelongsTo
    {
        return $this->belongsTo(DisciplineOffense::class, 'offense_id');
    }

    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DisciplineCaseAttachment::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(DisciplineIntervention::class)->latest('imposed_at');
    }

    public function confiscatedItems(): HasMany
    {
        return $this->hasMany(DisciplineConfiscatedItem::class);
    }
}
