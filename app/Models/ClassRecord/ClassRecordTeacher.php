<?php

namespace App\Models\ClassRecord;

use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Co-teacher pivot for shared class records (currently only the PEHM —
 * PE / Health / Music — special case). One row = one teacher owning one
 * subject on one shared class record.
 */
class ClassRecordTeacher extends Model
{
    protected $table = 'class_record_teachers';

    protected $fillable = [
        'class_record_id',
        'subject_id',
        'user_id',
        'is_primary',
    ];

    protected $casts = [
        'class_record_id' => 'integer',
        'subject_id' => 'integer',
        'user_id' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function classRecord(): BelongsTo
    {
        return $this->belongsTo(ClassRecord::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
