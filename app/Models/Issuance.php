<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Issuance extends Model
{
    protected $fillable = [
        'parent_issuance_id', 'document_kind', 'supplement_no', 'reference_number',
        'type', 'control_number', 'series_no', 'year', 'month',
        'title', 'content', 'content_text', 'attachment_path', 'attachment_filename', 'attachment_mime',
        'recipient_type', 'status', 'content_hash', 'qr_token', 'released_at', 'created_by',
        'archived_at', 'archived_by', 'archive_reason',
    ];

    protected $casts = [
        'released_at' => 'datetime',
        'series_no'   => 'integer',
        'year'        => 'integer',
        'month'       => 'integer',
        'supplement_no' => 'integer',
        'archived_at' => 'datetime',
    ];

    public static function typeLabels(): array
    {
        return IssuanceType::activeLabels();
    }

    protected static function booted(): void
    {
        static::creating(function (Issuance $model) {
            if (empty($model->qr_token)) {
                $model->qr_token = (string) Str::uuid();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients()
    {
        return $this->hasMany(IssuanceRecipient::class);
    }

    public function parentIssuance()
    {
        return $this->belongsTo(self::class, 'parent_issuance_id');
    }

    public function supplements()
    {
        return $this->hasMany(self::class, 'parent_issuance_id')
            ->orderBy('created_at');
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function signature()
    {
        return $this->morphOne(DigitalSignature::class, 'signable')->latestOfMany();
    }

    public function getTypeLabelAttribute(): string
    {
        return static::typeLabels()[$this->type] ?? $this->type;
    }

    public static function supplementKindLabels(): array
    {
        return [
            'addendum' => 'Addendum',
            'erratum' => 'Erratum',
            'corrigendum' => 'Corrigendum',
            'appendix' => 'Appendix',
        ];
    }

    public function getDocumentKindLabelAttribute(): string
    {
        return static::supplementKindLabels()[$this->document_kind] ?? 'Issuance';
    }

    public function getDisplayNumberAttribute(): string
    {
        return $this->reference_number ?: $this->control_number;
    }

    public function isSupplement(): bool
    {
        return $this->parent_issuance_id !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }
}
