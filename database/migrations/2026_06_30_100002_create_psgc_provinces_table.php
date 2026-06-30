<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('psgc_provinces', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name', 100);
            $table->string('region_code', 20);
            $table->enum('type', ['province', 'city'])->default('province');
            $table->index('region_code');
        });
    }
    public function down(): void { Schema::dropIfExists('psgc_provinces'); }
};
