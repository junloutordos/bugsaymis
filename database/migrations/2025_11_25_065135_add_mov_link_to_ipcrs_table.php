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
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->string('mov_link')->nullable()->after('accomplishment')->comment('Link to Means of Verification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->dropColumn('mov_link');
        });
    }
};
