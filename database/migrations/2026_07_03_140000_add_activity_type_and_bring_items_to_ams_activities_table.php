<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ams_activities', function (Blueprint $table) {
            $table->enum('activity_type', ['in_house', 'training_workshop_seminar'])
                ->default('in_house')->after('title');
            $table->text('what_to_bring')->nullable()->after('venue');
        });
    }

    public function down(): void
    {
        Schema::table('ams_activities', function (Blueprint $table) {
            $table->dropColumn(['activity_type', 'what_to_bring']);
        });
    }
};
