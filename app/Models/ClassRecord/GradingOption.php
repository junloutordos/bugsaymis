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
        'applicable_quarters',
        'owner_designation_id',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'applicable_quarters'  => 'array',
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

    /** Top-level categories only (parents + standalone leaves). */
    public function topLevelCategories(): HasMany
    {
        return $this->hasMany(GradingCategory::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * Leaf categories carry the weight and hold assessments. A category is a
     * leaf when it has no children among the option's categories.
     */
    public function leafCategories()
    {
        $all = $this->relationLoaded('categories') ? $this->categories : $this->categories()->get();
        $parentIds = $all->pluck('parent_id')->filter()->unique()->all();

        return $all->reject(fn ($cat) => in_array($cat->id, $parentIds, true))->values();
    }

    /** null applicable_quarters = applies to all quarters. */
    public function appliesToQuarter(int $quarter): bool
    {
        $quarters = $this->applicable_quarters;
        if (empty($quarters)) {
            return true;
        }

        return in_array($quarter, array_map('intval', $quarters), true);
    }

    public function classRecords(): HasMany
    {
        return $this->hasMany(ClassRecord::class);
    }
}
