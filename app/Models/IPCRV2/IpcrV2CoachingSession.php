<?php

namespace App\Models\IPCRV2;

use Illuminate\Database\Eloquent\Model;

class IpcrV2CoachingSession extends Model
{
    protected $table = 'ipcr_v2_coaching_sessions';

    protected $fillable = [
        'ipcr_v2_id', 'activity_type', 'mechanism', 'meeting_date',
        'channel_memo', 'channel_others', 'remarks',
        'conducted_by_name', 'conducted_at', 'noted_by_name', 'noted_at',
    ];

    protected $casts = [
        'meeting_date' => 'date:Y-m-d',
        'channel_memo' => 'boolean',
        'conducted_at' => 'datetime',
        'noted_at' => 'datetime',
    ];

    public function ipcr()
    {
        return $this->belongsTo(IpcrV2Record::class, 'ipcr_v2_id');
    }
}
