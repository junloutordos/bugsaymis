<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRecordIlaDate extends Model
{
    protected $table = 'class_record_ila_dates';

    protected $fillable = [
        'class_record_quarter_id',
        'date',
        'is_auto_generated',
        'sort_order',
    ];

    protected $casts = [
        'date'              => 'date:Y-m-d',
        'is_auto_generated' => 'boolean',
        'sort_order'        => 'integer',
    ];

    public function quarter(): BelongsTo
    {
        return $this->belongsTo(ClassRecordQuarter::class, 'class_record_quarter_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ClassRecordIlaRecord::class, 'class_record_ila_date_id');
    }
}
