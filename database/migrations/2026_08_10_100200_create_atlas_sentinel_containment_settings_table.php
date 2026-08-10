<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atlas_sentinel_containment_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_contain_enabled')->default(false);
            $table->unsignedSmallInteger('auto_release_minutes')->default(30);
            $table->unsignedInteger('max_half_open_connections')->default(100);
            $table->unsignedInteger('max_distinct_ips_per_minute')->default(50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atlas_sentinel_containment_settings');
    }
};
