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
        'document_date' => 'date:Y-m-d',
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
            'pre_employment' => 'Pre-Employment Requirements',
            'appointment'    => 'Appointment Papers',
            'eligibility'    => 'Civil Service Eligibility',
            'pds'            => 'Personal Data Sheet (CSC Form 212)',
            'service_record' => 'Service Record',
            'performance'    => 'Performance Evaluations',
            'training'       => 'Training & Development',
            'leave_record'   => 'Leave Records',
            'rewards'        => 'Rewards & Recognition',
            'disciplinary'   => 'Disciplinary Actions',
            'saln'           => 'SALN',
            'clearance'      => 'Clearances',
            'other'          => 'Other Documents',
            // legacy compat
            'medical'        => 'Medical Records',
            'leave'          => 'Leave Records',
            default          => ucfirst($this->category),
        };
    }
}
