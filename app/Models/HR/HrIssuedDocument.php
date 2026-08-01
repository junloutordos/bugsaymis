<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrIssuedDocument extends Model
{
    protected $table = 'hr_issued_documents';

    protected $hidden = ['s3_path'];

    protected $fillable = [
        'request_id', 'control_number', 'version', 's3_path', 'content_hash',
        'qr_token', 'issued_by', 'issued_at',
    ];

    protected $casts = ['issued_at' => 'datetime', 'version' => 'integer'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(HrDocumentRequest::class, 'request_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
