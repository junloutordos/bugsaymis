<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssuanceRecipient extends Model
{
    protected $fillable = [
        'issuance_id', 'user_id', 'office_id', 'notified_at', 'acknowledged_at',
    ];

    protected $casts = [
        'notified_at'    => 'datetime',
        'acknowledged_at'=> 'datetime',
    ];

    public function issuance()
    {
        return $this->belongsTo(Issuance::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }
}
