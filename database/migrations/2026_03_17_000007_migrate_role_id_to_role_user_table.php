<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time data migration: reads each user's comma-separated role_id string
 * (legacy column) and inserts the corresponding rows into the new role_user
 * pivot table. The legacy role_id column is kept intact for backward
 * compatibility with any code that still reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')->select('id', 'role_id')->get();

        foreach ($users as $user) {
            if (empty($user->role_id)) {
                continue;
            }

            $roleIds = array_filter(
                array_map('trim', explode(',', $user->role_id))
            );

            foreach ($roleIds as $roleId) {
                if (! is_numeric($roleId)) {
                    continue;
                }

                // Skip if role doesn't exist or pivot row already present
                $roleExists = DB::table('roles')->where('id', $roleId)->exists();
                if (! $roleExists) {
                    continue;
                }

                DB::table('role_user')->insertOrIgnore([
                    'user_id' => $user->id,
                    'role_id' => (int) $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Remove all rows that could have been seeded from role_id
        // (safe to truncate — the legacy role_id column is still intact)
        DB::table('role_user')->truncate();
    }
};
