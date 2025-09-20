<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PMSDate extends Model
{
    use HasFactory;

    protected $table = 'ict_pms_dates';

    protected $fillable = [
        'ict_pms_id',
        'schedule_date',
    ];

    protected $casts = [
        'schedule_date' => 'date',
    ];

    /**
     * A PMSDate belongs to a PMS Schedule
     */
    public function pms()
    {
        return $this->belongsTo(PMS::class, 'ict_pms_id');
    }
}
