<?php

namespace App\Models\Supply;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyCategory extends Model
{
    protected $table = 'supply_categories';

    protected $fillable = ['name', 'account_code', 'type', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(SupplyItem::class, 'category_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'consumable'     => 'Consumable',
            'semi_expendable'=> 'Semi-Expendable',
            'equipment'      => 'Equipment / PPE',
            default          => ucfirst($this->type),
        };
    }
}
