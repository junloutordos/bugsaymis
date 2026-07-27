<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedBigInteger('homeroom_coordinator_id')->nullable()
                  ->after('adviser');

            $table->foreign('homeroom_coordinator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeign(['homeroom_coordinator_id']);
            $table->dropColumn('homeroom_coordinator_id');
        });
    }
};
