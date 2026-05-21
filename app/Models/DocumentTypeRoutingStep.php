<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTypeRoutingStep extends Model
{
    protected $fillable = [
        'document_type_id',
        'step_order',
        'role_name',
        'action_required',
        'lead_time_hours',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }
}
