<?php

namespace App\Models;

use App\Traits\HasApprovalSnapshots;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ITJobRequest extends Model
{
    use HasFactory, HasApprovalSnapshots;

    protected $table = 'it_job_requests';

    protected $fillable = [
        'itjr_no',
        'user_id',
        'facility_request_id',
        'category',
        'event_date',
        'posting_type',
        'title',
        'description',
        'status',
        'divisionchief_id',
        'assignedto',
        'ict_equipment_id',
        'dc_approval_date',
        'ocd_approval_date',
        'mis_assessment',
        'recommendation',
        'expected_completion_date',
        'action_taken',
        'completed_at',
        'pdf_path',
        'attendedby',
        'rating',
        'rating_remarks',
        'rated_at',
        'decline_reason',
        'declined_at',
        'priority',
        'queued_at',
    ];

    protected $casts = [
        'event_date' => 'date:Y-m-d',
        'queued_at'  => 'datetime',
    ];

    // Priority rank for queue ordering (lower = higher priority)
    public const PRIORITY_RANK = ['urgent' => 1, 'high' => 2, 'normal' => 3, 'low' => 4];

    public function scopeInQueue($query)
    {
        return $query
            ->whereIn('status', ['In Progress', 'MIS Assessed the Request'])
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('queued_at', 'asc');
    }

    /**
     * Relationship with User (requestor)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Auto-generate itjr_no in format yyyy-mm-sequence
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->itjr_no)) {
                $datePrefix = Carbon::now()->format('Y-m');
                $latest = static::where('itjr_no', 'like', $datePrefix . '-%')
                    ->orderBy('itjr_no', 'desc')
                    ->first();

                if ($latest) {
                    $lastSequence = (int) substr($latest->itjr_no, -4);
                    $nextSequence = str_pad($lastSequence + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $nextSequence = '0001';
                }

                $model->itjr_no = $datePrefix . '-' . $nextSequence;
            }
        });
    }
    public function trackingLogs()
    {
        return $this->hasMany(ITJRTrackingLog::class, 'it_job_request_id', 'id')
                    ->orderBy('created_at', 'desc'); // timeline oldest → newest
    }
    public function divisionChief()
    {
        return $this->belongsTo(User::class, 'divisionchief_id');
    }
    public function equipment()
    {
        return $this->belongsTo(ICTEquipment::class, 'ict_equipment_id');
    }
    protected $appends = ['assigned_personnel'];

    public function getAssignedPersonnelAttribute()
    {
        return $this->assignedTo?->name; // returns name directly
    }
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assignedto', 'id');
    }

    public function digitalSignatures()
    {
        return $this->morphMany(DigitalSignature::class, 'signable')->latest();
    }


}
