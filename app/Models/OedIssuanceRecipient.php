<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OedIssuanceRecipient extends Model
{
    protected $fillable = ['oed_issuance_id', 'user_id'];

    public function issuance()
    {
        return $this->belongsTo(OedIssuance::class, 'oed_issuance_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
