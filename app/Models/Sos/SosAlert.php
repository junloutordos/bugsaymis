<?php

namespace App\Models\Sos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SosAlert extends Model
{
    protected $fillable = [
        'triggerable_type', 'triggerable_id', 'alert_type', 'is_silent', 'status',
        'lat', 'lng', 'accuracy', 'geofence_zone_id', 'current_tier_order',
        'triggered_at', 'resolved_at', 'resolved_by', 'resolution_notes',
        'resolved_location_type', 'resolved_location_label',
        'resolved_building', 'resolved_room', 'resolved_source',
    ];

    protected $casts = [
        'is_silent'    => 'boolean',
        'lat'          => 'float',
        'lng'          => 'float',
        'accuracy'     => 'float',
        'triggered_at' => 'datetime',
        'resolved_at'  => 'datetime',
    ];

    public function triggerable(): MorphTo
    {
        return $this->morphTo();
    }

    public function events(): HasMany
    {
        return $this->hasMany(SosAlertEvent::class);
    }

    public function responders(): HasMany
    {
        return $this->hasMany(SosAlertResponder::class);
    }

    public function activeResponders(): HasMany
    {
        return $this->hasMany(SosAlertResponder::class)->whereNull('unclaimed_at');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public static function falseAlarmCount(string $triggerableType, int $triggerableId, int $windowDays): int
    {
        return static::where('triggerable_type', $triggerableType)
            ->where('triggerable_id', $triggerableId)
            ->where('status', 'false_alarm')
            ->where('triggered_at', '>=', now()->subDays($windowDays))
            ->count();
    }
}
