<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            // Drop the old string column if needed
            $table->dropColumn('assignedto');

            // Add integer column
            $table->unsignedBigInteger('assignedto')->nullable()->after('divisionchief_id');

            // Optional: foreign key to users table
            $table->foreign('assignedto')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropForeign(['assignedto']);
            $table->dropColumn('assignedto');
            $table->string('assignedto')->nullable();
        });
    }
};

