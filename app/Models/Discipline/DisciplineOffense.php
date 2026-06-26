<?php

namespace App\Models\Discipline;

use Illuminate\Database\Eloquent\Model;

class DisciplineOffense extends Model
{
    protected $fillable = [
        'code',
        'category',
        'level',
        'title',
        'description',
        'default_sanction',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'level'      => 'integer',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];
}
