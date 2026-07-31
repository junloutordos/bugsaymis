<?php

namespace App\Models\ALP;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlpActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'alp_activity_logs';

    protected $fillable = ['alp_program_cycle_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'metadata'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AlpProgramCycle::class, 'alp_program_cycle_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
