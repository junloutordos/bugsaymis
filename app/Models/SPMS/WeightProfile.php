<?php

namespace App\Models\SPMS;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightProfile extends Model
{
    use HasFactory;

    protected $table = 'spms_weight_profiles';

    protected $fillable = [
        'level', 'division_id', 'fiscal_year',
        'strategic_pct', 'core_pct', 'support_pct', 'core_subweights',
    ];

    protected $casts = [
        'strategic_pct' => 'decimal:2',
        'core_pct' => 'decimal:2',
        'support_pct' => 'decimal:2',
        'core_subweights' => 'array',
        'fiscal_year' => 'integer',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
