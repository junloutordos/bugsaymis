<?php

namespace App\Models\Sos;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SosEscalationTier extends Model
{
    protected $fillable = ['alert_type', 'order', 'role_id', 'timeout_minutes', 'channels', 'notify_external'];

    protected $casts = ['channels' => 'array', 'notify_external' => 'boolean'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sos_escalation_tier_users');
    }
}
