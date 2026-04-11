<?php

namespace App\Models;

use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    use HasFactory, HasApprovalSnapshots;

    protected $fillable = [
        'service_type', 'copies', 'sheets_per_set', 'date_needed', 'time_needed', 'purposes', 'details', 'status', 'requestor_id', 'decline_reason', 'declined_at'
    ];

    protected $casts = [
        'declined_at' => 'datetime',
    ];
    
        public function requester() {
            return $this->belongsTo(User::class, 'requestor_id');
        }
}
