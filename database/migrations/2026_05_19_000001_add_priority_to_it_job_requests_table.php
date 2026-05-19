<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->enum('priority', ['urgent', 'high', 'normal', 'low'])
                ->default('normal')
                ->after('rated_at');
        });
    }

    public function down(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
