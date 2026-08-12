<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Office extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'division_id',
        'unit_head',
        'qr_survey_token',
        'qr_survey_enabled',
    ];

    protected $casts = [
        'qr_survey_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Office $office) {
            if (empty($office->qr_survey_token)) {
                $office->qr_survey_token = static::generateUniqueToken();
            }
            if (! isset($office->attributes['qr_survey_enabled'])) {
                $office->qr_survey_enabled = true;
            }
        });
    }

    public static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(22);
        } while (static::where('qr_survey_token', $token)->exists());

        return $token;
    }

    public function division()
    {
        return $this->belongsTo(\App\Models\Division::class);
    }

    public function unitHeadUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'unit_head');
    }

    /**
     * Anonymous/general CSM responses submitted via this office's QR survey.
     */
    public function csmResponses(): MorphMany
    {
        return $this->morphMany(CsmResponse::class, 'respondable');
    }

    /**
     * Public URL for this office's QR-code-triggered satisfaction survey.
     */
    public function surveyUrl(): string
    {
        return route('csm.survey.show', $this->qr_survey_token);
    }
}
