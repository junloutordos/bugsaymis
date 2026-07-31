<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    use HasFactory;

    protected $table = 'consultations';

    protected $fillable = [
        'student_id',
        'requestor_id',
        'requestor', 'email', 'unit', 'reason', 'contact', 'status', 'nurse_id', 'scheduled_at', 'notes',
        'consultation_type', 'schedule_id',
        'day', 'time_start', 'time_end', 'date_attended',
        'requestor_type',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'date_attended' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
}
