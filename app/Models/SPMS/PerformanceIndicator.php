<?php

namespace App\Models\SPMS;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PerformanceIndicator extends Model
{
    use HasFactory;

    protected $table = 'spms_performance_indicators';

    protected $fillable = ['spms_outcome_id', 'description', 'target', 'budget', 'fiscal_year'];

    protected $casts = ['budget' => 'decimal:2', 'fiscal_year' => 'integer'];

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(Outcome::class, 'spms_outcome_id');
    }

    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(
            Division::class,
            'spms_division_performance_indicator',
            'spms_performance_indicator_id',
            'division_id'
        )->withTimestamps();
    }
}
