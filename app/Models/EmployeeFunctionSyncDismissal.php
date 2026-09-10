<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per (user, sync_source_key) that the user has manually deleted
 * from the Employee Functions page for an auto-synced (source_type=
 * load_assignment) function. EmployeeFunctionSyncService::upsertRow()
 * checks this before recreating a row for that same source — see that
 * class and the creating migration for the full rationale.
 */
class EmployeeFunctionSyncDismissal extends Model
{
    protected $table = 'employee_function_sync_dismissals';

    protected $fillable = ['user_id', 'sync_source_key'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
