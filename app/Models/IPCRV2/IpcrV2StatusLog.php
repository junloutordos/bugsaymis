<?php

namespace App\Models\IPCRV2;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class IpcrV2StatusLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'ipcr_v2_status_logs';

    protected $fillable = [
        'ipcr_v2_record_id', 'from_status', 'to_status', 'action_type',
        'remarks', 'actor_id', 'actor_role', 'signed_via_pin', 'signature_snapshot',
    ];

    protected $casts = [
        'signed_via_pin' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(IpcrV2Record::class, 'ipcr_v2_record_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
