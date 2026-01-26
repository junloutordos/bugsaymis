<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // nullable for backward compatibility; unique index for efficient lookup
            $table->string('badge_id')->nullable()->unique()->after('email')->comment('Biometric ID / Badge ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // drop unique index created by ->unique()
            $table->dropUnique('users_badge_id_unique');
            $table->dropColumn('badge_id');
        });
    }
};
