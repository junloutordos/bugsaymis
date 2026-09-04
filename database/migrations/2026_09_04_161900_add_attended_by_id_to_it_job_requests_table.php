<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('attended_by_id')->nullable()->after('attendedby');
            $table->foreign('attended_by_id')->references('id')->on('users')->onDelete('set null');
        });

        // Backfill: match the free-text 'attendedby' name against MIS-role users
        // (case/whitespace-insensitive), same matching logic already used in
        // MISDashboardController's workload query. 'users.name' has been stored
        // in both "Last, First M." and "First M. Last" formats over time (the
        // latter predates the Pre/Post-Nominal Titles rename), so match both.
        $misUsers = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'MIS')
            ->select('users.id', 'users.name')
            ->get();

        foreach ($misUsers as $misUser) {
            $variants = [$misUser->name];

            if (str_contains($misUser->name, ',')) {
                [$last, $first] = array_map('trim', explode(',', $misUser->name, 2));
                $variants[] = "{$first} {$last}";
            }

            DB::table('it_job_requests')
                ->whereNull('attended_by_id')
                ->whereIn(DB::raw('LOWER(TRIM(attendedby))'), array_map(fn ($v) => strtolower(trim($v)), $variants))
                ->update(['attended_by_id' => $misUser->id]);
        }
    }

    public function down(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropForeign(['attended_by_id']);
            $table->dropColumn('attended_by_id');
        });
    }
};
