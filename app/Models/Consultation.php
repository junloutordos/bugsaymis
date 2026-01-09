<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    use HasFactory;

    protected $table = 'consultations';

    protected $fillable = [
        'requestor','email','unit','reason','contact','status','nurse_id','scheduled_at','notes'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];
}
