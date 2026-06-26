<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Selection extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'approved_by',
        'approval_status',
        'approval_date',
        'disapproval_reason',
    ];

    protected $casts = [
        'approval_date' => 'date:Y-m-d',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
