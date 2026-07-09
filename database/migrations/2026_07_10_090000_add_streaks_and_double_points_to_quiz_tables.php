<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_players', function (Blueprint $table) {
            $table->unsignedInteger('current_streak')->default(0)->after('total_score');
            $table->unsignedInteger('best_streak')->default(0)->after('current_streak');
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->boolean('double_points')->default(false)->after('points_base');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_players', function (Blueprint $table) {
            $table->dropColumn(['current_streak', 'best_streak']);
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn('double_points');
        });
    }
};
