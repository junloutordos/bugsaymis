<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTypeRoutingStep extends Model
{
    protected $fillable = [
        'document_type_id',
        'step_order',
        'office_id',
        'assigned_user_id',
        'role_name',       // kept for backward compat, prefer office+user
        'action_required',
        'lead_time_hours',
        'is_required',
    ];

    public function office()
    {
        return $this->belongsTo(\App\Models\Office::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_user_id');
    }

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }
}
