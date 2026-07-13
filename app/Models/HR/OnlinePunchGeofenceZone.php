<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlinePunchGeofenceZone extends Model
{
    protected $table = 'online_punch_geofence_zones';

    protected $fillable = [
        'label',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'latitude'      => 'decimal:7',
        'longitude'     => 'decimal:7',
        'radius_meters' => 'integer',
        'is_active'     => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
