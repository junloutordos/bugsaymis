<?php

namespace App\Models\ALP;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlpOfficer extends Model
{
    protected $table = 'alp_officers';

    protected $fillable = ['alp_program_cycle_id', 'alp_membership_id', 'position', 'sort_order', 'registrar_status', 'academic_standing_snapshot', 'certified_by', 'certified_at', 'certification_remarks'];

    protected $casts = ['academic_standing_snapshot' => 'array', 'certified_at' => 'datetime'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AlpProgramCycle::class, 'alp_program_cycle_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(AlpMembership::class, 'alp_membership_id');
    }

    public function certifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }
}
