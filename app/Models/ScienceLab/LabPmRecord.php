<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabPmRecord extends Model
{
    protected $table = 'lab_pm_records';

    protected $fillable = [
        'lab_pm_schedule_id', 'month', 'status', 'performed_on', 'remarks',
    ];

    protected $casts = [
        'month' => 'integer',
        'performed_on' => 'date:Y-m-d',
    ];

    public function schedule() { return $this->belongsTo(LabPmSchedule::class, 'lab_pm_schedule_id'); }
}
