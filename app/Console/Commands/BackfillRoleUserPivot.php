<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillRoleUserPivot extends Command
{
    protected $signature = 'backfill:role_user_pivot';
    protected $description = 'Sync the role_user pivot table from the legacy role_id column for users missing pivot rows (mobile-registered accounts never wrote the pivot)';

    public function handle()
    {
        $roles = Role::pluck('id', 'id');

        $users = User::whereNotNull('role_id')
            ->where('role_id', '!=', '')
            ->doesntHave('roles')
            ->get(['id', 'role_id']);

        $this->info("Found {$users->count()} user(s) with a legacy role_id but no role_user pivot rows.");

        $synced = 0;
        foreach ($users as $user) {
            $roleId = (int) $user->role_id;

            if (! $roles->has($roleId)) {
                $this->warn("User #{$user->id}: role_id '{$user->role_id}' does not match any role, skipped.");
                continue;
            }

            $user->roles()->syncWithoutDetaching([$roleId]);
            $synced++;
        }

        $this->info("Synced pivot rows for {$synced} user(s).");
        return 0;
    }
}
