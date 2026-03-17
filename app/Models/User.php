<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use App\Models\Permission;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * New pivot-based many-to-many roles relationship.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Legacy single-role relationship (kept for backward compatibility).
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function office()
    {
        return $this->belongsTo(\App\Models\Office::class);
    }

    public function pmsPerformed()
    {
        return $this->hasMany(PMS::class, 'performed_by');
    }

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

    // ─── Permission cache (per-request, cleared on role change) ──────────────

    /** @var array<string,bool>|null */
    private ?array $permissionCache = null;

    /**
     * Eagerly load all permission names for this user into an in-memory cache.
     * Called automatically on first permission check; also callable explicitly
     * after assigning/revoking roles so the cache stays fresh.
     */
    public function loadPermissions(): static
    {
        $this->permissionCache = Permission::query()
            ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('role_user', 'permission_role.role_id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->id)
            ->pluck('permissions.name')
            ->flip()           // name => index, so isset() is O(1)
            ->all();

        return $this;
    }

    /**
     * Discard the cached permission set (call after syncing roles/permissions).
     */
    public function clearPermissionCache(): static
    {
        $this->permissionCache = null;
        return $this;
    }

    // ─── Role helpers (new pivot-based) ──────────────────────────────────────

    /**
     * Check if the user is a SuperAdmin (bypasses all permission checks).
     */
    public function isSuperAdmin(): bool
    {
        return $this->roles->contains('name', 'Administrator');
    }

    /**
     * Check if the user has a specific role by name.
     * Checks the pivot relationship first, falls back to legacy role_id.
     */
    public function hasRole(string $roleName): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('name', $roleName);
        }

        return $this->getRolesCollection()->contains('name', $roleName);
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roleNames): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn($r) => in_array($r->name, $roleNames));
        }

        return $this->getRolesCollection()->contains(fn($r) => in_array($r->name, $roleNames));
    }

    /**
     * Check if the user has a specific permission (via any assigned role).
     * SuperAdmins bypass this check. Results are cached for the request lifetime.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->permissionCache === null) {
            $this->loadPermissions();
        }

        return isset($this->permissionCache[$permission]);
    }

    /**
     * Check if the user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->permissionCache === null) {
            $this->loadPermissions();
        }

        foreach ($permissions as $p) {
            if (isset($this->permissionCache[$p])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has ALL of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->permissionCache === null) {
            $this->loadPermissions();
        }

        foreach ($permissions as $p) {
            if (! isset($this->permissionCache[$p])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return all cached permission names as a flat array.
     * Useful for passing to the frontend (Inertia shared data).
     */
    public function getPermissions(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::pluck('name')->all();
        }

        if ($this->permissionCache === null) {
            $this->loadPermissions();
        }

        return array_keys($this->permissionCache);
    }

    // ─── Legacy helpers (kept for backward compatibility) ─────────────────────

    /**
     * Returns an array of role IDs from the legacy comma-separated role_id field.
     */
    public function getRoleIdsArray(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->role_id ?? ''))));
    }

    /**
     * Returns a Collection of Role models from the legacy role_id field.
     */
    public function getRolesCollection(): Collection
    {
        $ids = $this->getRoleIdsArray();
        if (empty($ids)) return collect();
        return Role::whereIn('id', $ids)->get();
    }

    /**
     * Get the primary (first) role name for display purposes.
     */
    public function getRoleName(): string
    {
        if ($this->relationLoaded('roles') && $this->roles->isNotEmpty()) {
            return $this->roles->first()->name;
        }

        return $this->getRolesCollection()->first()?->name ?? '';
    }

    /**
     * Query scope: filter users who have a specific role.
     * Checks the pivot table first, falls back to legacy FIND_IN_SET.
     */
    public function scopeHavingRole($query, string $roleName)
    {
        return $query->whereHas('roles', fn($q) => $q->where('name', $roleName));
    }
}
