<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ipcrs', function (Blueprint $table) {
            

            // Add self Q/E/T
            $table->integer('self_quality')->nullable();
            $table->integer('self_efficiency')->nullable();
            $table->integer('self_timeliness')->nullable();
            $table->decimal('self_rating', 3, 2)->nullable(); // average

            // Add supervisor Q/E/T
            $table->integer('supervisor_quality')->nullable();
            $table->integer('supervisor_efficiency')->nullable();
            $table->integer('supervisor_timeliness')->nullable();
            $table->decimal('supervisor_rating', 3, 2)->nullable(); // average
        });
    }

    public function down(): void
    {
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->dropColumn([
                'self_quality', 'self_efficiency', 'self_timeliness', 'self_rating',
                'supervisor_quality', 'supervisor_efficiency', 'supervisor_timeliness', 'supervisor_rating',
            ]);

            $table->integer('self_rating')->nullable();
            $table->integer('supervisor_rating')->nullable();
        });
    }
};
