<?php

namespace App\Models\Discipline;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineIntervention extends Model
{
    protected $fillable = [
        'discipline_case_id',
        'intervention_type',
        'description',
        'imposed_by',
        'imposed_at',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'imposed_at' => 'datetime',
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
    ];

    public function disciplineCase(): BelongsTo
    {
        return $this->belongsTo(DisciplineCase::class);
    }

    public function imposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imposed_by');
    }
}
