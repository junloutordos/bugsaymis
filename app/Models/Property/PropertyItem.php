<?php

namespace App\Models\Property;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyItem extends Model
{
    protected $table = 'property_items';

    protected $fillable = [
        'property_number', 'description', 'category_id', 'unit', 'quantity',
        'unit_cost', 'acquisition_date', 'acquisition_mode', 'supplier_name',
        'purchase_order_id', 'iar_item_id', 'brand', 'model', 'serial_number',
        'location', 'current_officer_id', 'status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'quantity'         => 'decimal:3',
        'unit_cost'        => 'decimal:4',
        'acquisition_date' => 'date:Y-m-d',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'category_id');
    }

    public function currentOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_officer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parItems(): HasMany
    {
        return $this->hasMany(ParItem::class, 'property_item_id');
    }

    public function icsItems(): HasMany
    {
        return $this->hasMany(IcsItem::class, 'property_item_id');
    }

    public function bsrItems(): HasMany
    {
        return $this->hasMany(BsrItem::class, 'property_item_id');
    }

    public function disposalItems(): HasMany
    {
        return $this->hasMany(DisposalItem::class, 'property_item_id');
    }

    public function totalCost(): float
    {
        return round((float) $this->quantity * (float) $this->unit_cost, 2);
    }

    public function residualValue(): float
    {
        return round($this->totalCost() * (float) $this->category?->residual_rate, 2);
    }

    public function depreciableAmount(): float
    {
        return $this->totalCost() - $this->residualValue();
    }

    public function monthlyDepreciation(): float
    {
        $months = $this->category?->usefulLifeMonths() ?? 60;
        if ($months <= 0) return 0;
        return round($this->depreciableAmount() / $months, 4);
    }

    public function accumulatedDepreciation(\DateTimeInterface|string|null $asOf = null): float
    {
        $asOf = $asOf ? \Carbon\Carbon::parse($asOf) : now();
        $startMonth = \Carbon\Carbon::parse($this->acquisition_date)->startOfMonth()->addMonth();

        if ($asOf->lt($startMonth)) return 0;

        $months = min($startMonth->diffInMonths($asOf) + 1, $this->category?->usefulLifeMonths() ?? 60);
        return round($this->monthlyDepreciation() * $months, 2);
    }

    public function bookValue(\DateTimeInterface|string|null $asOf = null): float
    {
        return max($this->residualValue(), $this->totalCost() - $this->accumulatedDepreciation($asOf));
    }

    public function isServiceable(): bool
    {
        return $this->status === 'serviceable';
    }

    public static function generateNumber(string $type = 'PAR'): string
    {
        $year  = now()->format('Y');
        $month = now()->format('m');
        $seq   = static::whereYear('created_at', $year)->whereMonth('created_at', $month)->count() + 1;
        return sprintf('%s-%s-%s-%04d', $type, $year, $month, $seq);
    }
}
