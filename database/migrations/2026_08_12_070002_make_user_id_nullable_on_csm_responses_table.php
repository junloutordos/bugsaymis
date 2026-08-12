<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand: anonymous QR-sourced CSM responses have no authenticated user.
        // Drop the FK first (it was created with cascadeOnDelete + NOT NULL), then
        // re-add the column as nullable with the same FK behavior.
        Schema::table('csm_responses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('csm_responses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('csm_responses', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('csm_responses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('csm_responses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('csm_responses', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
