<?php

namespace App\Models\ALP;

use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlpProgramCycle extends Model
{
    use HasApprovalSnapshots;

    protected $table = 'alp_program_cycles';

    protected $fillable = [
        'alp_program_id', 'school_year_id', 'adviser_assignment_id', 'adviser_id', 'coordinator_id',
        'cycle_type', 'status', 'application_data', 'deficiency_notes', 'submitted_at', 'reviewed_at',
        'recommended_at', 'accredited_at', 'closed_at', 'created_by',
    ];

    protected $casts = [
        'application_data' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime',
        'recommended_at' => 'datetime', 'accredited_at' => 'datetime', 'closed_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(AlpProgram::class, 'alp_program_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function adviserAssignment(): BelongsTo
    {
        return $this->belongsTo(LoadAssignment::class);
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(AlpMembership::class);
    }

    public function officers(): HasMany
    {
        return $this->hasMany(AlpOfficer::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AlpDocument::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(AlpActivity::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AlpSession::class);
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(AlpFinancialEntry::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(AlpReport::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(AlpActivityLog::class);
    }
}
