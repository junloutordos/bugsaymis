<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GantimpalaNomination extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'source',
        'nominee_user_id',
        'nominee_name',
        'nominee_title',
        'nominee_department',
        'nominee_address',
        'nominee_contact_number',
        'date_submitted',
        'nominator_user_id',
        'nominator_name',
        'nominator_address',
        'nominator_contact_number',
        'nominator_email',
        'activity_conducted',
        'activity_date',
        'venue_location',
        'other_information',
        'nominator_signature_path',
        'supervisor_name',
        'supervisor_signature_path',
        'endorsed_at',
        'endorsed_by',
        'status',
        'remarks',
        'decided_by',
        'decided_at',
        'submitted_ip',
        'kiosk_device',
        'pdf_path',
        'reference_no',
    ];

    protected $casts = [
        'date_submitted' => 'date:Y-m-d',
        'activity_date'  => 'date:Y-m-d',
        'endorsed_at'    => 'datetime',
        'decided_at'     => 'datetime',
    ];

    public function nominee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominee_user_id');
    }

    public function nominator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominator_user_id');
    }

    public function endorser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endorsed_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GantimpalaLog::class, 'nomination_id')->orderBy('acted_at');
    }
}
