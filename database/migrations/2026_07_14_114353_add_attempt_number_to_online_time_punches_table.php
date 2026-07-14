<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_time_punches', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempt_number')->default(1)->after('punch_type');
        });

        // Retries now create additional rows per slot (up to a capped number
        // of attempts) instead of a single row — widen the uniqueness key to
        // include attempt_number so each retry gets its own row.
        Schema::table('online_time_punches', function (Blueprint $table) {
            $table->dropUnique('online_time_punches_unique');
            $table->unique(['user_id', 'work_date', 'punch_type', 'attempt_number'], 'online_time_punches_unique');
        });
    }

    public function down(): void
    {
        Schema::table('online_time_punches', function (Blueprint $table) {
            $table->dropUnique('online_time_punches_unique');
            $table->unique(['user_id', 'work_date', 'punch_type'], 'online_time_punches_unique');
        });

        Schema::table('online_time_punches', function (Blueprint $table) {
            $table->dropColumn('attempt_number');
        });
    }
};
