<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ITJRTrackingLog extends Model
{
    use HasFactory;

    protected $table = 'itjr_tracking_logs';

    protected $fillable = [
        'it_job_request_id',
        'status',
        'remarks',
        'updated_by',
    ];

    public function jobRequest()
    {
        return $this->belongsTo(ITJobRequest::class, 'it_job_request_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
