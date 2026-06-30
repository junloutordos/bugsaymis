<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorReport extends Model
{
    protected $table = 'error_reports';

    protected $fillable = [
        'report_no',
        'user_id',
        'title',
        'description',
        'page_url',
        'browser_info',
        'screenshot_path',
        'status',
        'priority',
        'assigned_to',
        'action_taken',
        'resolved_at',
    ];

    protected $casts = [
        'browser_info' => 'array',
        'resolved_at'  => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'resolved'    => 'Resolved',
            default       => ucfirst($this->status),
        };
    }

    public function priorityLabel(): string
    {
        return ucfirst($this->priority);
    }
}
