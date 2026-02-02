<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'sex',
        'badge_id',
        'email',
        'password',
        'role_id',
        'position',
        'division_id',
        'office_id',
        'profile_picture',
        'electronic_signature',
        'emp_category',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    public function pmsPerformed()
    {
        return $this->hasMany(PMS::class, 'performed_by');
    }
    public function division()
    {
        return $this->belongsTo(Division::class);
    }
    public function office()
    {
        return $this->belongsTo(\App\Models\Office::class);
    }
    
    // app/Models/User.php

    public function workDistributionPlans()
    {
        return $this->belongsToMany(WorkDistributionPlan::class, 'work_distribution_plan_user')
            ->withPivot('role_in_plan')
            ->withTimestamps();
    }
    
    public function ipcrs()
    {
        return $this->hasMany(IPCR::class, 'user_id');
    }


}
