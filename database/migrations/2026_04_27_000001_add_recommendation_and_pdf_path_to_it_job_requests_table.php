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
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->text('recommendation')->nullable()->after('mis_assessment');
            $table->string('pdf_path', 500)->nullable()->after('recommendation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropColumn(['recommendation', 'pdf_path']);
        });
    }
};
