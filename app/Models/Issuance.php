<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Issuance extends Model
{
    protected $fillable = [
        'type', 'control_number', 'series_no', 'year',
        'title', 'content', 'attachment_path', 'attachment_filename', 'attachment_mime',
        'recipient_type', 'status', 'content_hash', 'qr_token', 'released_at', 'created_by',
    ];

    protected $casts = [
        'released_at' => 'datetime',
        'series_no'   => 'integer',
        'year'        => 'integer',
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

    public function signature()
    {
        return $this->morphOne(DigitalSignature::class, 'signable')->latestOfMany();
    }

    public function getTypeLabelAttribute(): string
    {
        return static::typeLabels()[$this->type] ?? $this->type;
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
