<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgencyOutcome extends Model
{
    use HasFactory;

    protected $table = 'agency_org_outcomes';

    protected $fillable = [
        'outcome',
        'sub_outcome',
        'function_type', // ✅ new field    
    ];
    
}
