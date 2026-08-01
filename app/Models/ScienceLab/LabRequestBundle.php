<?php

namespace App\Models\ScienceLab;

use Illuminate\Database\Eloquent\Model;

class LabRequestBundle extends Model
{
    protected $fillable = [
        'reference_no', 'school_year_id', 'requested_by_id', 'requester_student_id',
        'requester_name', 'requester_type', 'assigned_endorser_id', 'assigned_approver_id',
        'status', 'submitted_at', 'completed_at', 'csm_completed_at', 'rated_at', 'cancellation_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'csm_completed_at' => 'datetime',
        'rated_at' => 'datetime',
    ];

    public function requester() { return $this->belongsTo(\App\Models\User::class, 'requested_by_id'); }
    public function endorser() { return $this->belongsTo(\App\Models\User::class, 'assigned_endorser_id'); }
    public function approver() { return $this->belongsTo(\App\Models\User::class, 'assigned_approver_id'); }
    public function reservation() { return $this->hasOne(LabReservation::class, 'bundle_id'); }
    public function equipmentRequest() { return $this->hasOne(LabEquipmentRequest::class, 'bundle_id'); }
    public function reagentRequest() { return $this->hasOne(LabReagentRequest::class, 'bundle_id'); }
    public function histories() { return $this->hasMany(LabRequestStatusHistory::class, 'bundle_id'); }
    public function csmResponse() { return $this->morphOne(\App\Models\CsmResponse::class, 'respondable'); }
}
