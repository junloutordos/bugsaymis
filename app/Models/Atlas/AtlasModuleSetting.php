<?php

namespace App\Models\Atlas;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtlasModuleSetting extends Model
{
    protected $primaryKey = 'module_key';
    protected $keyType    = 'string';
    public $incrementing  = false;

    protected $fillable = [
        'module_key',
        'owner_id',
        'sla_hours',
        'version',
        'notes',
    ];

    protected $casts = [
        'sla_hours' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
