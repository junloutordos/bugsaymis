<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PDSWorkExperienceSheet extends Model
{
    protected $table = 'pds_work_experience_sheets';

    protected $fillable = [
        'pds_id',
        'from_date',
        'to_date',
        'position',
        'office_unit',
        'immediate_supervisor',
        'agency_organization_location',
        'accomplishments',
        'summary_of_duties',
        'sort_order',
    ];

    protected $casts = [
        'accomplishments'   => 'array',
        'summary_of_duties' => 'array',
        'sort_order'        => 'integer',
    ];

    public function pds(): BelongsTo
    {
        return $this->belongsTo(Pds::class);
    }
}
