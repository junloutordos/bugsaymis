<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyCategory extends Model
{
    protected $table = 'property_categories';

    protected $fillable = [
        'name', 'account_code', 'type', 'useful_life_years',
        'residual_rate', 'description', 'is_active',
    ];

    protected $casts = [
        'useful_life_years' => 'integer',
        'residual_rate'     => 'decimal:4',
        'is_active'         => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PropertyItem::class, 'category_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'semi_expendable' => 'Semi-Expendable',
            'equipment'       => 'Equipment / PPE',
            default           => ucfirst($this->type),
        };
    }

    public function usefulLifeMonths(): int
    {
        return $this->useful_life_years * 12;
    }
}
