<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin-triggered "Fix Now" request, separate from the rule-based
 * auto_execute pipeline (IctAgentRemediationDispatcher/ict_remediation_rules)
 * — this bypasses the enabled/auto_execute gate entirely since a human
 * explicitly asked for it. Delivered on the device's next checkin
 * (up to ~20 min later, no push channel to the agent exists).
 */
class IctEquipmentManualRemediationRequest extends Model
{
    protected $table = 'ict_equipment_manual_remediation_requests';

    protected $fillable = [
        'device_id',
        'equipment_id',
        'action',
        'target',
        'requested_by',
        'status',
        'result',
        'details',
        'requested_at',
        'delivered_at',
        'completed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }

    public function equipment()
    {
        return $this->belongsTo(ICTEquipment::class, 'equipment_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
