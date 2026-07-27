<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRecordIlaRecord extends Model
{
    protected $table = 'class_record_ila_records';

    protected $fillable = [
        'class_record_ila_date_id',
        'class_record_student_id',
        'status',
    ];

    public function ilaDate(): BelongsTo
    {
        return $this->belongsTo(ClassRecordIlaDate::class, 'class_record_ila_date_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(ClassRecordStudent::class, 'class_record_student_id');
    }
}
