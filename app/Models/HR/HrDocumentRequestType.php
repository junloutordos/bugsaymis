<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrDocumentRequestType extends Model
{
    protected $table = 'hr_document_request_types';

    protected $fillable = [
        'code', 'name', 'description', 'instructions', 'template_key',
        'requires_ocd_approval', 'signatory', 'sla_business_days',
        'supports_digital', 'supports_pickup', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'requires_ocd_approval' => 'boolean',
        'supports_digital' => 'boolean',
        'supports_pickup' => 'boolean',
        'is_active' => 'boolean',
        'sla_business_days' => 'integer',
        'sort_order' => 'integer',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(HrDocumentRequest::class, 'request_type_id');
    }
}
