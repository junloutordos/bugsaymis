<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabFirstAidCheck extends Model
{
    protected $table = 'lab_first_aid_checks';

    protected $fillable = [
        'room_id', 'check_date', 'means_of_verification', 'inclusion', 'document_path', 'items', 'is_complete', 'checked_by_id', 'remarks',
    ];

    protected $casts = [
        'check_date' => 'date:Y-m-d',
        'items' => 'array',
        'is_complete' => 'boolean',
    ];

    public function room() { return $this->belongsTo(\App\Models\Room::class); }

    public function checkedBy() { return $this->belongsTo(\App\Models\User::class, 'checked_by_id'); }
}
