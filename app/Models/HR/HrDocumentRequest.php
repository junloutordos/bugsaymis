<?php

namespace App\Models\HR;

use App\Models\DigitalSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class HrDocumentRequest extends Model
{
    protected $table = 'hr_document_requests';

    protected $fillable = [
        'reference_no', 'requester_id', 'request_type_id', 'processor_id',
        'ocd_approver_id', 'status', 'purpose', 'addressed_to',
        'destination_agency', 'needed_at', 'copies', 'delivery_method',
        'special_instructions', 'employee_visible_remarks', 'internal_notes',
        'document_payload', 'submitted_at', 'due_at', 'started_at',
        'routed_to_ocd_at', 'ocd_approved_at', 'ready_at', 'issued_at',
        'cancelled_at', 'rejected_at',
    ];

    protected $casts = [
        'needed_at' => 'date:Y-m-d',
        'document_payload' => 'array',
        'submitted_at' => 'datetime',
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'routed_to_ocd_at' => 'datetime',
        'ocd_approved_at' => 'datetime',
        'ready_at' => 'datetime',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rejected_at' => 'datetime',
        'copies' => 'integer',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(HrDocumentRequestType::class, 'request_type_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processor_id');
    }

    public function ocdApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ocd_approver_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(HrDocumentRequestEvent::class, 'request_id')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HrDocumentRequestAttachment::class, 'request_id');
    }

    public function issuedDocuments(): HasMany
    {
        return $this->hasMany(HrIssuedDocument::class, 'request_id')->latest('version');
    }

    public function latestIssuedDocument()
    {
        return $this->hasOne(HrIssuedDocument::class, 'request_id')->latestOfMany('version');
    }

    public function ocdSignature(): MorphOne
    {
        return $this->morphOne(DigitalSignature::class, 'signable')
            ->where('metadata->stage', 'ocd_approval')
            ->latestOfMany();
    }

    public function signatures(): MorphMany
    {
        return $this->morphMany(DigitalSignature::class, 'signable')->latest();
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->requester_id === (int) $user->id;
    }
}
