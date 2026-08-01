<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDocumentRequestAttachment extends Model
{
    protected $table = 'hr_document_request_attachments';

    protected $hidden = ['s3_path'];

    protected $fillable = [
        'request_id', 'uploaded_by', 'original_filename', 'mime_type',
        'file_size', 's3_path',
    ];

    protected $casts = ['file_size' => 'integer'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(HrDocumentRequest::class, 'request_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
