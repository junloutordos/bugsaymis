<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRecordLwopPeriod extends Model
{
    protected $table = 'hr_service_record_lwop_periods';
    protected $guarded = [];

    protected $casts = [
        'date_from' => 'date:Y-m-d',
        'date_to' => 'date:Y-m-d',
        'days' => 'decimal:3',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ServiceRecordEntry::class, 'service_record_entry_id');
    }
}
