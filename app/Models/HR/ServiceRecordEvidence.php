<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRecordEvidence extends Model
{
    protected $table = 'hr_service_record_evidence';
    protected $guarded = [];
    protected $hidden = ['s3_path'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ServiceRecordEntry::class, 'service_record_entry_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
