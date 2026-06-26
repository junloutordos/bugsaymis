<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accomplishment extends Model
{
    protected $fillable = [
        'user_id',
        'ipcr_plan_id',
        'accomplishment_date',
        'description',
    ];

    protected $casts = [
        'accomplishment_date' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ipcrPlan()
    {
        return $this->belongsTo(EmployeeIPCRPlan::class, 'ipcr_plan_id');
    }

    public function photos()
    {
        return $this->hasMany(AccomplishmentPhoto::class);
    }
}
