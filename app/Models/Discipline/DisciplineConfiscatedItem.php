<?php

namespace App\Models\Discipline;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineConfiscatedItem extends Model
{
    protected $fillable = [
        'receipt_no',
        'discipline_case_id',
        'student_id',
        'item_description',
        'place',
        'reason',
        'confiscated_by',
        'confiscated_at',
        'status',
        'claimed_at',
        'claim_ack_role',
        'claim_ack_by',
        'claim_remarks',
    ];

    protected $casts = [
        'confiscated_at' => 'datetime',
        'claimed_at'     => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function disciplineCase(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class);
    }

    public function confiscatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confiscated_by');
    }

    public function claimAckBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claim_ack_by');
    }
}
