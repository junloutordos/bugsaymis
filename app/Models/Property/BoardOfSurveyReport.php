<?php

namespace App\Models\Property;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardOfSurveyReport extends Model
{
    protected $table = 'board_of_survey_reports';

    protected $fillable = [
        'bsr_number', 'inspection_date', 'board_members',
        'findings', 'recommendation', 'status',
        'approved_by', 'approved_at', 'remarks', 'created_by',
    ];

    protected $casts = [
        'inspection_date' => 'date:Y-m-d',
        'board_members'   => 'array',
        'approved_at'     => 'datetime',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BsrItem::class, 'bsr_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'approved'  => 'Approved',
            'cancelled' => 'Cancelled',
            default     => ucfirst($this->status),
        };
    }

    public static function generateNumber(): string
    {
        $year  = now()->format('Y');
        $seq   = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('BSR-%s-%04d', $year, $seq);
    }
}
