<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class BackfillAccountType extends Command
{
    protected $signature = 'backfill:account_type';
    protected $description = 'Set account_type (employee/parent/student) for existing users, derived from their current Student/Parent role, or employee otherwise';

    public function handle()
    {
        $users = User::whereNull('account_type')->with('roles:id,name')->get(['id', 'account_type']);

        $this->info("Found {$users->count()} user(s) with no account_type set.");

        $counts = ['employee' => 0, 'parent' => 0, 'student' => 0];

        foreach ($users as $user) {
            $roleNames = $user->roles->pluck('name');

            $type = match (true) {
                $roleNames->contains('Student') => 'student',
                $roleNames->contains('Parent')  => 'parent',
                default                          => 'employee',
            };

            $user->update(['account_type' => $type]);
            $counts[$type]++;
        }

        $this->info("Backfilled: {$counts['employee']} employee(s), {$counts['parent']} parent(s), {$counts['student']} student(s).");
        return 0;
    }
}
