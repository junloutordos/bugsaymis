<?php

namespace App\Models\PPMP;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PpmpStatusHistory extends Model
{
    protected $table = 'ppmp_status_history';

    public $timestamps = false;

    protected $fillable = [
        'ppmp_id',
        'from_status',
        'to_status',
        'acted_by',
        'remarks',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function ppmp()
    {
        return $this->belongsTo(Ppmp::class, 'ppmp_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
