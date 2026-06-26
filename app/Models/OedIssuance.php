<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OedIssuance extends Model
{
    protected $fillable = [
        'category_code', 'reference_no', 'title', 'description', 'content_text',
        'issued_date', 'effective_date',
        'file_path', 'file_name', 'file_mime', 'file_size',
        'status', 'superseded_by_id', 'recipient_type', 'uploaded_by',
    ];

    protected $casts = [
        'issued_date'    => 'date:Y-m-d',
        'effective_date' => 'date:Y-m-d',
        'file_size'      => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(OedIssuanceCategory::class, 'category_code', 'code');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function supersededBy()
    {
        return $this->belongsTo(OedIssuance::class, 'superseded_by_id');
    }

    public function supersedes()
    {
        return $this->hasOne(OedIssuance::class, 'superseded_by_id');
    }

    public function acknowledgments()
    {
        return $this->hasMany(OedIssuanceAcknowledgment::class);
    }

    public function recipients()
    {
        return $this->hasMany(OedIssuanceRecipient::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function acknowledgedByUser(int $userId): bool
    {
        return $this->relationLoaded('acknowledgments')
            ? $this->acknowledgments->contains('user_id', $userId)
            : $this->acknowledgments()->where('user_id', $userId)->exists();
    }

    public function isVisibleTo(int $userId): bool
    {
        return $this->recipient_type === 'all'
            || $this->recipients()->where('user_id', $userId)->exists();
    }
}
