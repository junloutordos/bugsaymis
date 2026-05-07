<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingOption extends Model
{
    protected $table = 'grading_options';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(GradingCategory::class)->orderBy('sort_order');
    }

    public function classRecords(): HasMany
    {
        return $this->hasMany(ClassRecord::class);
    }
}
