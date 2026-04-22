<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ict_pms_history', function (Blueprint $table) {
            $table->dropForeign(['ict_pms_id']);
            $table->unsignedBigInteger('ict_pms_id')->nullable()->change();

            $table->foreign('ict_pms_id')
                ->references('id')
                ->on('ict_pms')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ict_pms_history', function (Blueprint $table) {
            //
        });
    }
};
