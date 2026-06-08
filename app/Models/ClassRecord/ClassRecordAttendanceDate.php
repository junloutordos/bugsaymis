<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRecordAttendanceDate extends Model
{
    protected $table = 'class_record_attendance_dates';

    protected $fillable = [
        'class_record_quarter_id',
        'date',
        'sort_order',
    ];

    protected $casts = [
        'date'       => 'date:Y-m-d',
        'sort_order' => 'integer',
    ];

    public function quarter(): BelongsTo
    {
        return $this->belongsTo(ClassRecordQuarter::class, 'class_record_quarter_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ClassRecordAttendanceRecord::class, 'class_record_attendance_date_id');
    }
}
