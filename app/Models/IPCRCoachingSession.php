<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IPCRCoachingSession extends Model
{
    protected $table = 'ipcr_coaching_sessions';

    protected $fillable = [
        'employee_ipcr_id',
        'created_by',
        'activity_type',
        'mechanism',
        'meeting_date',
        'channel_memo',
        'channel_others',
        'remarks',
        'conducted_by_name',
        'conducted_at',
        'noted_by_name',
        'noted_at',
    ];

    protected $casts = [
        'meeting_date'  => 'date',
        'channel_memo'  => 'boolean',
        'conducted_at'  => 'date',
        'noted_at'      => 'date',
    ];

    public function employeeIpcr()
    {
        return $this->belongsTo(EmployeeIPCR::class, 'employee_ipcr_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
