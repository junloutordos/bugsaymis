<?php

namespace App\Models\PPMP;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PpmpCatalogue extends Model
{
    protected $table = 'ppmp_catalogue';

    protected $fillable = [
        'fiscal_year',
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
        'unit_cost'            => 'decimal:2',
        'price_validity_date'  => 'date',
        'is_active'            => 'boolean',
        'fiscal_year'          => 'integer',
        'uploaded_at'          => 'datetime',
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
}
