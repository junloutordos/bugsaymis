<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->index('status', 'itjr_status_idx');
            $table->index('title', 'itjr_title_idx');
            $table->index('category', 'itjr_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropIndex('itjr_status_idx');
            $table->dropIndex('itjr_title_idx');
            $table->dropIndex('itjr_category_idx');
        });
    }
};
