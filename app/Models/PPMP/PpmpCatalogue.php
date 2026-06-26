<?php

namespace App\Models\PPMP;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PpmpCatalogue extends Model
{
    protected $table = 'ppmp_catalogue';

    protected $fillable = [
        'fiscal_year',
        'part',
        'category_group',
        'row_position',
        'is_price_fixed',
        'stock_number',
        'description',
        'unit',
        'unit_cost',
        'price_validity_date',
        'is_active',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'unit_cost'           => 'decimal:2',
        'price_validity_date' => 'date:Y-m-d',
        'is_active'           => 'boolean',
        'is_price_fixed'      => 'boolean',
        'fiscal_year'         => 'integer',
        'part'                => 'integer',
        'row_position'        => 'integer',
        'uploaded_at'         => 'datetime',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function ppmpItems()
    {
        return $this->hasMany(PpmpItem::class, 'catalogue_id');
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('fiscal_year', $year)->where('is_active', true);
    }

    public function scopePart($query, int $part)
    {
        return $query->where('part', $part);
    }

    public function effectiveUnitCost(): float
    {
        return (float) $this->unit_cost;
    }
}
