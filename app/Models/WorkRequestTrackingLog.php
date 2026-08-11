<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkRequestTrackingLog extends Model
{
    use HasFactory;

    protected $table = 'work_request_tracking_logs';

    protected $fillable = [
        'work_request_id',
        'status',
        'remarks',
        'updated_by',
    ];

    public function workRequest()
    {
        return $this->belongsTo(WorkRequest::class, 'work_request_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
