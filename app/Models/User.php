<?php

namespace App\Models;

use App\Models\FacultyLoading\SstPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use App\Models\Permission;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'sex',
        'badge_id',
        'biometric_id',
        'status',
        'email',
        'password',
        'role_id',
        'position',
        'specialization',
        'sst_position_id',
        'division_id',
        'office_id',
        'profile_picture',
        'electronic_signature',
        'signature_pin',
        'emp_category',
        'employee_no',
        'on_study_leave',
        'salary_grade',
        'salary_step',
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
            'signature_pin'     => 'hashed',
            'on_study_leave'    => 'boolean',
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

    public function digitalSignatures()
    {
        return $this->hasMany(DigitalSignature::class, 'signer_id');
    }

    // ─── Organizational Unit relationships ────────────────────────────────────

    /** All unit assignments (including historical). */
    public function unitAssignments()
    {
        return $this->hasMany(\App\Models\HR\EmployeeUnitAssignment::class);
    }

    /** Currently active primary unit assignment. */
    public function primaryUnitAssignment()
    {
        return $this->hasOne(\App\Models\HR\EmployeeUnitAssignment::class)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->whereNull('deleted_at')
            ->latest('effective_date');
    }

    /** The primary organizational unit this employee belongs to. */
    public function primaryUnit()
    {
        return $this->hasOneThrough(
            \App\Models\OrganizationalUnit::class,
            \App\Models\HR\EmployeeUnitAssignment::class,
            'user_id',
            'id',
            'id',
            'organizational_unit_id'
        )->where('employee_unit_assignments.is_primary', true)
         ->whereNull('employee_unit_assignments.end_date')
         ->whereNull('employee_unit_assignments.deleted_at');
    }

    /** SST position level (for Faculty Loading overload pay). */
    public function sstPosition()
    {
        return $this->belongsTo(SstPosition::class, 'sst_position_id');
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

    // ─── HR / Payroll Relationships ────────────────────────────────────────────

    public function employeeProfile()
    {
        return $this->hasOne(\App\Models\HR\EmployeeProfile::class);
    }

    public function employeeSchedules()
    {
        return $this->hasMany(\App\Models\HR\EmployeeSchedule::class);
    }

    public function dtrRecords()
    {
        return $this->hasMany(\App\Models\HR\DtrRecord::class);
    }

    public function leaveCredits()
    {
        return $this->hasMany(\App\Models\HR\LeaveCredit::class);
    }

    public function leaveApplications()
    {
        return $this->hasMany(\App\Models\HR\LeaveApplication::class);
    }

    public function payrollRecords()
    {
        return $this->hasMany(\App\Models\Payroll\PayrollRecord::class);
    }

    public function deductionConfigs()
    {
        return $this->hasMany(\App\Models\Payroll\EmployeeDeductionConfig::class);
    }

    // ─── SALN Relationships ───────────────────────────────────────────────────

    public function salnRecords()
    {
        return $this->hasMany(\App\Models\SALN\SalnRecord::class);
    }

    // ─── Chat Relationships ───────────────────────────────────────────────────

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
            ->withPivot(['last_read_at', 'left_at'])
            ->withTimestamps()
            ->whereNull('conversation_user.left_at')
            ->latest('conversations.updated_at');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(\App\Models\PushSubscription::class);
    }
}
