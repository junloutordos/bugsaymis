<?php

namespace App\Models\ALP;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlpSession extends Model
{
    protected $table = 'alp_sessions';

    protected $fillable = ['alp_program_cycle_id', 'alp_activity_id', 'session_date', 'start_time', 'end_time', 'topic', 'venue', 'status', 'created_by'];

    protected $casts = ['session_date' => 'date:Y-m-d'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AlpProgramCycle::class, 'alp_program_cycle_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(AlpActivity::class, 'alp_activity_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AlpAttendance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
