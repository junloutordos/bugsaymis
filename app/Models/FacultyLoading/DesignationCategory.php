<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DesignationCategory extends Model
{
    protected $table = 'designation_categories';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }

    public function activeDesignations(): HasMany
    {
        return $this->hasMany(Designation::class)->where('is_active', true);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
