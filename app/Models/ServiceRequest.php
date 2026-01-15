<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_type', 'copies', 'sheets_per_set', 'date_needed', 'time_needed', 'purposes', 'details', 'status', 'requestor_id', 'unit', 'division_chief_id', 'decline_reason', 'declined_at'
    ];

    protected $casts = [
        'declined_at' => 'datetime',
    ];
}
