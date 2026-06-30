<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('psgc_cities', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name', 100);
            $table->string('province_code', 20)->nullable();
            $table->string('region_code', 20);
            $table->index('province_code');
            $table->index('region_code');
        });
    }
    public function down(): void { Schema::dropIfExists('psgc_cities'); }
};
