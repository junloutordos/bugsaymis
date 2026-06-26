<?php

namespace App\Models\Property;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrder extends Model
{
    protected $table = 'work_orders';

    protected $fillable = [
        'work_order_number', 'property_item_id', 'description',
        'requested_by', 'assigned_to', 'priority', 'status',
        'estimated_cost', 'actual_cost', 'requested_date',
        'target_date', 'completed_date', 'remarks', 'created_by',
    ];

    protected $casts = [
        'estimated_cost'  => 'decimal:2',
        'actual_cost'     => 'decimal:2',
        'requested_date'  => 'date:Y-m-d',
        'target_date'     => 'date:Y-m-d',
        'completed_date'  => 'date:Y-m-d',
    ];

    public function propertyItem(): BelongsTo
    {
        return $this->belongsTo(PropertyItem::class, 'property_item_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'     => 'Pending',
            'in_progress' => 'In Progress',
            'completed'   => 'Completed',
            'cancelled'   => 'Cancelled',
            default       => ucfirst($this->status),
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            'low'    => 'Low',
            'normal' => 'Normal',
            'high'   => 'High',
            'urgent' => 'Urgent',
            default  => ucfirst($this->priority),
        };
    }

    public static function generateNumber(): string
    {
        $year  = now()->format('Y');
        $month = now()->format('m');
        $seq   = static::whereYear('created_at', $year)->whereMonth('created_at', $month)->count() + 1;
        return sprintf('WO-%s-%s-%04d', $year, $month, $seq);
    }
}
