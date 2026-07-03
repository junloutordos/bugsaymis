<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabSafetyProcedure extends Model
{
    protected $table = 'lab_safety_procedures';

    protected $fillable = [
        'room_id', 'unit', 'title', 'body', 'document_path', 'determined_by_id', 'is_posted', 'effective_date', 'status',
    ];

    protected $casts = [
        'is_posted' => 'boolean',
        'effective_date' => 'date:Y-m-d',
    ];

    public function room() { return $this->belongsTo(\App\Models\Room::class); }

    public function determinedBy() { return $this->belongsTo(\App\Models\User::class, 'determined_by_id'); }
}
