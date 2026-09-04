<?php

namespace App\Models\OPCR;

use Illuminate\Database\Eloquent\Model;

class OpcrPeriod extends Model
{
    protected $fillable = [
        'fiscal_year',
        'period_label',
        'is_current',
        'campus_director_name',
        'oic_campus_director_name',
        'executive_director_name',
        'commitment_statement',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function indicators()
    {
        return $this->hasMany(OpcrIndicator::class);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
