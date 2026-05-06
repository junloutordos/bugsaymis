<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingCategory extends Model
{
    protected $table = 'grading_categories';

    protected $fillable = [
        'grading_option_id',
        'name',
        'code',
        'weight',
        'max_assessments',
        'sort_order',
    ];

    protected $casts = [
        'weight'          => 'float',
        'max_assessments' => 'integer',
        'sort_order'      => 'integer',
    ];

    public function gradingOption(): BelongsTo
    {
        return $this->belongsTo(GradingOption::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ClassRecordAssessment::class);
    }
}
