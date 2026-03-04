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
        'status',
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

    /**
     * Returns an array of role IDs parsed from the comma-separated role_id field.
     */
    public function getRoleIdsArray(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->role_id ?? ''))));
    }

    /**
     * Returns a Collection of all Role models assigned to this user.
     */
    public function getRolesCollection()
    {
        $ids = $this->getRoleIdsArray();
        if (empty($ids)) return collect();
        return Role::whereIn('id', $ids)->get();
    }

    /**
     * Check if the user has a specific role by name.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->getRolesCollection()->contains('name', $roleName);
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->getRolesCollection()->contains(fn($r) => in_array($r->name, $roleNames));
    }

    /**
     * Get the primary (first) role name for display purposes.
     */
    public function getRoleName(): string
    {
        return $this->getRolesCollection()->first()?->name ?? '';
    }

    /**
     * Query scope: filter users who have a specific role (supports comma-separated role_id).
     * Usage: User::havingRole('DivisionChief')->get()
     */
    public function scopeHavingRole($query, string $roleName)
    {
        $role = Role::where('name', $roleName)->first();
        if (! $role) return $query->whereRaw('0 = 1');
        return $query->whereRaw('FIND_IN_SET(?, role_id)', [$role->id]);
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
