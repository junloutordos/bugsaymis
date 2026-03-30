<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhysicianSchedule extends Model
{
    use HasFactory;

    protected $table = 'physician_schedule';

    protected $fillable = [
        'schedule_date',
        'time_start',
        'time_end',
    ];
}
