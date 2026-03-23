<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    protected $table = 'employee_documents';

    protected $fillable = [
        'user_id',
        'uploaded_by',
        'category',
        'title',
        'description',
        'document_date',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'document_date' => 'date',
        'file_size'     => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'appointment'    => 'Appointment',
            'pds'            => 'PDS/Personal Data Sheet',
            'service_record' => 'Service Record',
            'performance'    => 'Performance Ratings',
            'eligibility'    => 'Eligibility/Civil Service',
            'training'       => 'Training Certificates',
            'medical'        => 'Medical Records',
            'leave'          => 'Leave Records',
            'other'          => 'Other Documents',
            default          => ucfirst($this->category),
        };
    }
}
