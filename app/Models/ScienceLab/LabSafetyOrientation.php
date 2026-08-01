<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabSafetyOrientation extends Model
{
    protected $table = 'lab_safety_orientations';

    protected $fillable = [
        'room_id', 'school_year_id', 'orientation_date', 'conducted_by_id', 'grade_level_section', 'topic', 'attendees', 'document_path', 'remarks',
    ];

    protected $casts = [
        'orientation_date' => 'date:Y-m-d',
        'attendees' => 'array',
    ];

    public function room() { return $this->belongsTo(\App\Models\Room::class); }

    public function conductedBy() { return $this->belongsTo(\App\Models\User::class, 'conducted_by_id'); }
}
