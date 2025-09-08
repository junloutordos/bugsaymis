<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. Administrator, Faculty, etc.
            $table->timestamps();
        });

        // Update users table to use role_id instead of ENUM
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('id');

            // optional: add foreign key
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');

            // drop old enum column
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');

            // restore old role enum if you rollback
            $table->enum('role', ['Administrator','Faculty','Staff','Student','Parent']);
        });

        Schema::dropIfExists('roles');
    }
};
