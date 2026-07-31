<?php

namespace App\Models\ALP;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlpAttendance extends Model
{
    protected $table = 'alp_attendance';

    protected $fillable = ['alp_session_id', 'alp_membership_id', 'status', 'remarks', 'recorded_by'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AlpSession::class, 'alp_session_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(AlpMembership::class, 'alp_membership_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
