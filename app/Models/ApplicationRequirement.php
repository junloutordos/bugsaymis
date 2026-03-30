<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationRequirement extends Model
{
    protected $fillable = [
        'name',
        'description',
        'accepted_formats',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function jobItems()
    {
        return $this->belongsToMany(
            JobItem::class,
            'job_item_requirements',
            'requirement_id',
            'job_item_id'
        )->withPivot('is_mandatory')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
