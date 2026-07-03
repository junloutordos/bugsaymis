<?php

namespace App\Models\StudentClearance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentClearanceActivityLog extends Model
{
    protected $fillable = [
        'student_clearance_id',
        'student_clearance_item_id',
        'actor_type',
        'actor_id',
        'action',
        'metadata',
    ];

    protected $casts = [
        'student_clearance_id'      => 'integer',
        'student_clearance_item_id' => 'integer',
        'actor_id'                  => 'integer',
        'metadata'                  => 'array',
    ];

    public function clearance(): BelongsTo
    {
        return $this->belongsTo(StudentClearance::class, 'student_clearance_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StudentClearanceItem::class, 'student_clearance_item_id');
    }
}
