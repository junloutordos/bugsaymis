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
            $table->tinyInteger('rating')->nullable();
            $table->text('rating_remarks')->nullable();
            $table->timestamp('rated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_remarks', 'rated_at']);
        });
    }
};
