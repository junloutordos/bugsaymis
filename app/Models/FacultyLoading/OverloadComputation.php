<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OverloadComputation extends Model
{
    protected $table = 'overload_computations';

    protected $fillable = [
        'user_id',
        'faculty_load_id',
        'school_year_id',
        'academic_term_id',
        'annual_rate',
        'overload_units',
        'overload_hours',
        'phtr',
        'total_overload_pay',
        'term_weeks',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'annual_rate'       => 'decimal:2',
        'overload_units'    => 'decimal:2',
        'overload_hours'    => 'decimal:2',
        'phtr'              => 'decimal:4',
        'total_overload_pay'=> 'decimal:2',
        'term_weeks'        => 'integer',
        'approved_at'       => 'datetime',
    ];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function facultyLoad(): BelongsTo
    {
        return $this->belongsTo(FacultyLoad::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isApproved(): bool  { return $this->status === 'approved'; }
    public function isPaid(): bool      { return $this->status === 'paid'; }

    public function scopePending($query)     { return $query->where('status', 'pending'); }
    public function scopeForApproval($query) { return $query->where('status', 'for_approval'); }
    public function scopeApproved($query)    { return $query->where('status', 'approved'); }
}
