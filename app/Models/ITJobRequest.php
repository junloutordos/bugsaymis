<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ITJobRequest extends Model
{
    use HasFactory;

    protected $table = 'it_job_requests';

    protected $fillable = [
        'itjr_no',
        'user_id',
        'category',
        'title',
        'description',
        'status',
        'divisionchief',
        'assignedto',
        'dc_approval_date',
        'ocd_approval_date',
        'mis_assessment',
        'expected_completion_date',
        'action_taken',
        'completed_at',
        'attendedby',
        'feedback',
    ];

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
}
