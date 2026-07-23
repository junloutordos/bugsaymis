<?php

namespace App\Models\ClassRecord;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRecordStudent extends Model
{
    protected $table = 'class_record_students';

    protected $fillable = [
        'class_record_quarter_id',
        'student_id',
        'family_name',
        'given_name',
        'middle_initial',
        'sex',
        'sequence_number',
        'is_active',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'sequence_number' => 'integer',
        'is_active' => 'boolean',
    ];

    public function quarter(): BelongsTo
    {
        return $this->belongsTo(ClassRecordQuarter::class, 'class_record_quarter_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ClassRecordScore::class);
    }

    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? ' '.$this->middle_initial.'.' : '';

        return "{$this->family_name}, {$this->given_name}{$mi}";
    }
}
