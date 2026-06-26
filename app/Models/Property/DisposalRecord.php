<?php

namespace App\Models\Property;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisposalRecord extends Model
{
    protected $table = 'disposal_records';

    protected $fillable = [
        'disposal_number', 'bsr_id', 'disposal_date', 'disposal_method',
        'proceeds_amount', 'recipient', 'status',
        'approved_by', 'approved_at', 'remarks', 'created_by',
    ];

    protected $casts = [
        'disposal_date'    => 'date:Y-m-d',
        'proceeds_amount'  => 'decimal:2',
        'approved_at'      => 'datetime',
    ];

    public function bsr(): BelongsTo
    {
        return $this->belongsTo(BoardOfSurveyReport::class, 'bsr_id');
    }

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
        return $this->hasMany(DisposalItem::class, 'disposal_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'approved'  => 'Approved',
            'completed' => 'Completed',
            default     => ucfirst($this->status),
        };
    }

    public function methodLabel(): string
    {
        return match ($this->disposal_method) {
            'auction'      => 'Public Auction',
            'donation'     => 'Donation',
            'destruction'  => 'Destruction',
            'condemnation' => 'Condemnation',
            'trade_in'     => 'Trade-In',
            default        => ucfirst($this->disposal_method),
        };
    }

    public static function generateNumber(): string
    {
        $year  = now()->format('Y');
        $seq   = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('DISP-%s-%04d', $year, $seq);
    }
}
