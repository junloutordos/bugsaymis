<?php

namespace App\Models\Administration;

use App\Models\DigitalSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

class CoaCertificate extends Model
{
    protected $table = 'coa_certificates';

    protected $fillable = [
        'coa_visit_id',
        'visitor_name',
        'organization',
        'position',
        'email',
        'control_number',
        'qr_token',
        'content_hash',
        'email_status',
        'emailed_at',
    ];

    protected $casts = [
        'emailed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CoaCertificate $model) {
            if (empty($model->qr_token)) {
                $model->qr_token = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function visit(): BelongsTo
    {
        return $this->belongsTo(CoaVisit::class, 'coa_visit_id');
    }

    public function signature(): MorphOne
    {
        return $this->morphOne(DigitalSignature::class, 'signable')->latestOfMany();
    }
}
