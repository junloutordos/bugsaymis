<?php

namespace App\Models\SPMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outcome extends Model
{
    use HasFactory;

    protected $table = 'spms_outcomes';

    protected $fillable = ['outcome', 'sub_outcome', 'function_type', 'fiscal_year'];

    protected $casts = ['fiscal_year' => 'integer'];

    public function indicators(): HasMany
    {
        return $this->hasMany(PerformanceIndicator::class, 'spms_outcome_id');
    }
}
