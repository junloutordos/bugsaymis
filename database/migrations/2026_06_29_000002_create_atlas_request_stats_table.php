<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atlas_request_stats', function (Blueprint $table) {
            $table->id();
            $table->string('route_name', 200);
            $table->dateTime('hour_bucket');
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedBigInteger('total_duration_ms')->default(0);
            $table->unsignedBigInteger('cache_hits')->default(0);
            $table->unsignedBigInteger('cache_misses')->default(0);
            $table->unsignedBigInteger('query_count')->default(0);
            $table->unsignedBigInteger('total_query_ms')->default(0);

            $table->unique(['route_name', 'hour_bucket']);
            $table->index('hour_bucket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atlas_request_stats');
    }
};
