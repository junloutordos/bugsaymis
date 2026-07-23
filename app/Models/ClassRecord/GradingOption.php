<?php

namespace App\Models\ClassRecord;

use App\Models\FacultyLoading\Designation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingOption extends Model
{
    protected $table = 'grading_options';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'owner_designation_id',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'owner_designation_id' => 'integer',
    ];

    public function ownerDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'owner_designation_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(GradingCategory::class)->orderBy('sort_order');
    }

    public function classRecords(): HasMany
    {
        return $this->hasMany(ClassRecord::class);
    }
}
